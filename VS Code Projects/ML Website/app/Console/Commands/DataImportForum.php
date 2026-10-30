<?php

namespace App\Console\Commands;

use App\Models\ForumQuestion;
use App\Models\Dataset;
use App\Models\DataSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataImportForum extends Command
{
    protected $signature = 'data:import-forum {--max-pages=10 : Maximum pages per site}';
    protected $description = 'Import forum questions from Stack Exchange Astronomy and Space sites';

    private array $planetaryTags = ['planet', 'solar-system', 'mars', 'moon', 'venus', 'jupiter', 'saturn', 'exoplanet', 'atmosphere'];
    private array $stellarTags = ['star', 'stellar-evolution', 'supernova', 'black-hole', 'neutron-star', 'sun', 'nuclear-fusion', 'stellar-astrophysics'];

    public function handle(): int
    {
        $maxPages = (int)$this->option('max-pages');
        
        $this->info("Importing forum questions from Stack Exchange (max {$maxPages} pages per site)...");

        $allQuestions = [];
        
        foreach (['astronomy', 'space'] as $site) {
            $this->info("Fetching from {$site}.stackexchange.com...");
            $questions = $this->fetchQuestions($site, $maxPages);
            $allQuestions = array_merge($allQuestions, $questions);
            $this->info("  Retrieved " . count($questions) . " questions from {$site}");
        }

        $categorized = $this->categorizeQuestions($allQuestions);
        
        $source = DataSource::updateOrCreate(
            ['key' => 'stackexchange_astronomy_space'],
            [
                'title' => 'Stack Exchange Astronomy & Space Exploration Questions',
                'publisher' => 'Stack Exchange Inc.',
                'url' => 'https://api.stackexchange.com/',
                'license' => 'CC BY-SA 4.0',
                'retrieved_at' => now(),
                'row_count' => array_sum(array_map('count', $categorized)),
                'citation_text' => 'Data sourced from Astronomy Stack Exchange and Space Exploration Stack Exchange via the Stack Exchange API. Content licensed under CC BY-SA 4.0. Retrieved ' . now()->format('Y-m-d') . '.',
            ]
        );

        $dataset = Dataset::updateOrCreate(
            ['mission_key' => 'forum', 'key' => 'stackexchange_qa'],
            [
                'title' => 'Stack Exchange Q&A',
                'description' => 'Astronomy and Space Exploration Stack Exchange questions categorized into Planetary Science, Stellar Physics, and Space Exploration Tech.',
                'publisher' => $source->publisher,
                'url' => $source->url,
                'license' => $source->license,
                'row_count' => $source->row_count,
                'retrieved_at' => $source->retrieved_at,
                'citation_text' => $source->citation_text,
                'feature_schema' => json_encode([
                    'question_text' => ['unit' => 'text', 'min' => 10, 'max' => 500, 'step' => 1],
                ]),
                'label_names' => json_encode(['Planetary Science', 'Stellar Physics', 'Space Exploration Tech']),
                'is_default' => true,
            ]
        );

        ForumQuestion::where('dataset_id', $dataset->id)->delete();

        $batch = [];
        foreach ($categorized as $category => $questions) {
            foreach ($questions as $q) {
                $batch[] = [
                    'source_site' => $q['source_site'],
                    'title' => $q['title'],
                    'tags' => json_encode($q['tags']),
                    'category' => $category,
                    'url' => $q['url'],
                    'score' => $q['score'],
                    'dataset_id' => $dataset->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($batch)) {
            foreach (array_chunk($batch, 500) as $chunk) {
                ForumQuestion::insert($chunk);
            }
        }

        foreach ($categorized as $cat => $qs) {
            $this->info("  {$cat}: " . count($qs) . " questions");
        }
        $this->info("Import complete. Total: {$dataset->row_count} rows loaded.");
        
        return Command::SUCCESS;
    }

    private function fetchQuestions(string $site, int $maxPages): array
    {
        $allQuestions = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore && $page <= $maxPages) {
            try {
                $response = Http::timeout(30)->get("https://api.stackexchange.com/2.3/questions", [
                    'order' => 'desc',
                    'sort' => 'votes',
                    'site' => $site,
                    'pagesize' => 100,
                    'page' => $page,
                    'filter' => '!9Z(-wzftf', // includes tags
                ]);

                if (!$response->successful()) {
                    $this->warn("API error for {$site} page {$page}: HTTP {$response->status()}");
                    break;
                }

                $data = $response->json();
                $items = $data['items'] ?? [];
                $hasMore = $data['has_more'] ?? false;

                foreach ($items as $item) {
                    $allQuestions[] = [
                        'source_site' => $site,
                        'title' => $item['title'],
                        'tags' => $item['tags'] ?? [],
                        'url' => $item['link'],
                        'score' => $item['score'] ?? 0,
                    ];
                }

                $page++;
                
                if ($hasMore) {
                    sleep(1); // Respect rate limit
                }
            } catch (\Exception $e) {
                $this->warn("Exception fetching {$site} page {$page}: {$e->getMessage()}");
                break;
            }
        }

        return $allQuestions;
    }

    private function categorizeQuestions(array $questions): array
    {
        $categorized = [
            'Planetary Science' => [],
            'Stellar Physics' => [],
            'Space Exploration Tech' => [],
        ];

        foreach ($questions as $q) {
            if ($q['source_site'] === 'space') {
                $categorized['Space Exploration Tech'][] = $q;
                continue;
            }

            $tags = array_map('strtolower', $q['tags']);
            $planetaryMatch = false;
            $stellarMatch = false;

            foreach ($tags as $tag) {
                if (in_array($tag, $this->planetaryTags)) {
                    $planetaryMatch = true;
                }
                if (in_array($tag, $this->stellarTags)) {
                    $stellarMatch = true;
                }
            }

            if ($planetaryMatch && !$stellarMatch) {
                $categorized['Planetary Science'][] = $q;
            } elseif ($stellarMatch && !$planetaryMatch) {
                $categorized['Stellar Physics'][] = $q;
            }
        }

        // Balance and limit to ~300 per category
        foreach ($categorized as $cat => $qs) {
            shuffle($qs);
            $categorized[$cat] = array_slice($qs, 0, 300);
        }

        return $categorized;
    }
}