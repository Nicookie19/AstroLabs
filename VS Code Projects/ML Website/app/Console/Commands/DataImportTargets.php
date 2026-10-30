<?php

namespace App\Console\Commands;

use App\Models\TelescopeTarget;
use App\Models\Dataset;
use App\Models\DataSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DataImportTargets extends Command
{
    protected $signature = 'data:import-targets {--force : Force re-import even if data exists}';
    protected $description = 'Import telescope targets from OpenNGC (Messier objects) plus solar system bodies';

    public function handle(): int
    {
        $this->info('Importing telescope targets from OpenNGC...');

        $localFile = Storage::path('data/openncg.csv');
        $rawFile = Storage::path('raw/ngc.csv');
        
        $csvData = $this->getCsvData($localFile, $rawFile);
        if (!$csvData) {
            return Command::FAILURE;
        }

        $messierObjects = array_filter($csvData, function($row) {
            return !empty($row['M']) && is_numeric($row['M']);
        });

        $brightNGC = array_filter($csvData, function($row) {
            $mag = $this->magnitude($row);
            return empty($row['M']) && $mag !== null && $mag < 10;
        });

        $source = DataSource::updateOrCreate(
            ['key' => 'openncg_messier'],
            [
                'title' => 'OpenNGC - Messier Objects Catalog',
                'publisher' => 'Matteo Verga / OpenNGC',
                'url' => 'https://github.com/mattiaverga/OpenNGC',
                'license' => 'CC BY-SA 4.0',
                'retrieved_at' => now(),
                'row_count' => count($messierObjects) + 9,
                'citation_text' => 'Verga, M. (2024). OpenNGC: Open New General Catalogue. Retrieved from https://github.com/mattiaverga/OpenNGC. Plus solar system bodies from NASA/JPL planetary fact sheets.',
            ]
        );

        $this->importDataset('telescope', 'messier', 'Messier Objects', $messierObjects, $source, true, function($row) {
            $category = $this->categoryForType($row['Type'] ?? '');
            $distanceRank = $category === 'Galaxy' ? 3 : 2;
            
            return [
                'name' => "M{$row['M']} {$row['Name']}",
                'catalog_id' => "M{$row['M']}",
                'category' => $category,
                'distance_rank' => $distanceRank,
                'apparent_magnitude' => $this->magnitude($row),
                'constellation' => $row['Const'] ?? null,
            ];
        });

        $this->importDataset('telescope', 'bright_ngc_ic', 'Brightest NGC and IC Objects (mag < 10)', $brightNGC, $source, false, function($row) {
            $category = $this->categoryForType($row['Type'] ?? '');
            $distanceRank = $category === 'Galaxy' ? 3 : 2;
            
            return [
                'name' => $row['Name'],
                'catalog_id' => $row['Name'],
                'category' => $category,
                'distance_rank' => $distanceRank,
                'apparent_magnitude' => $this->magnitude($row),
                'constellation' => $row['Const'] ?? null,
            ];
        });

        $solarSystem = $this->getSolarSystemTargets();
        $this->importDataset('telescope', 'solar_system', 'Solar System Bodies', $solarSystem, $source, false, function($row) {
            return $row;
        });

        $this->info("Import complete.");
        return Command::SUCCESS;
    }

    private function importDataset(
        string $missionKey,
        string $key,
        string $title,
        array $data,
        DataSource $source,
        bool $isDefault,
        callable $mapper
    ): void {
        $mapped = array_map($mapper, $data);
        
        $dataset = Dataset::updateOrCreate(
            ['mission_key' => $missionKey, 'key' => $key],
            [
                'title' => $title,
                'description' => "Telescope target dataset for Telescope Setup Wizard mission.",
                'publisher' => $source->publisher,
                'url' => $source->url,
                'license' => $source->license,
                'row_count' => count($mapped),
                'retrieved_at' => $source->retrieved_at,
                'citation_text' => $source->citation_text,
                'feature_schema' => json_encode([
                    'bortle_scale' => ['unit' => 'Bortle scale', 'min' => 1, 'max' => 9, 'step' => 1],
                    'distance_rank' => ['unit' => 'rank', 'min' => 0, 'max' => 3, 'step' => 1],
                ]),
                'label_names' => json_encode(['Light-pollution filter', 'Colour planetary filter', 'No filter', 'Hydrogen-alpha filter', 'Neutral density filter']),
                'is_default' => $isDefault,
            ]
        );

        TelescopeTarget::where('dataset_id', $dataset->id)->delete();

        $batch = [];
        foreach ($mapped as $row) {
            $batch[] = array_merge($row, [
                'dataset_id' => $dataset->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!empty($batch)) {
            foreach (array_chunk($batch, 500) as $chunk) {
                TelescopeTarget::insert($chunk);
            }
        }

        $this->info("  Dataset '{$title}': " . count($mapped) . " targets imported.");
    }

    private function getCsvData(string $localFile, string $rawFile): ?array
    {
        if (file_exists($localFile)) {
            $this->info("Using local file: {$localFile}");
            return $this->parseCsv($localFile);
        }

        if (file_exists($rawFile)) {
            $this->info("Using raw file: {$rawFile}");
            return $this->parseCsv($rawFile);
        }

        $this->info('Downloading from OpenNGC GitHub...');
        $url = 'https://raw.githubusercontent.com/mattiaverga/OpenNGC/master/database_files/NGC.csv';
        
        try {
            $response = Http::timeout(60)->get($url);
            if (!$response->successful()) {
                throw new \Exception("HTTP {$response->status()}: {$response->body()}");
            }
            
            Storage::put('raw/ngc.csv', $response->body());
            $this->info("Downloaded and saved to storage/app/raw/ngc.csv");
            return $this->parseCsvFromString($response->body());
        } catch (\Exception $e) {
            $this->error("Download failed: {$e->getMessage()}");
            $this->error("Please manually download the CSV from:");
            $this->error("  https://raw.githubusercontent.com/mattiaverga/OpenNGC/master/database_files/NGC.csv");
            $this->error("And save it to: storage/app/raw/ngc.csv");
            return null;
        }
    }

    private function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Could not open file: {$filePath}");
        }

        $header = fgetcsv($handle, 0, ';');
        $data = [];
        
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) !== count($header)) continue;
            $data[] = array_combine($header, $row);
        }
        fclose($handle);
        return $data;
    }

    private function parseCsvFromString(string $content): array
    {
        $lines = explode("\n", trim($content));
        $header = str_getcsv(array_shift($lines), ';');
        $data = [];
        
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $row = str_getcsv($line, ';');
            if (count($row) !== count($header)) continue;
            $data[] = array_combine($header, $row);
        }
        return $data;
    }

    private function floatVal($val): ?float
    {
        if ($val === '' || $val === null || $val === '?') return null;
        $f = (float)$val;
        return is_nan($f) || is_infinite($f) ? null : $f;
    }

    private function magnitude(array $row): ?float
    {
        return $this->floatVal($row['V-Mag'] ?? $row['B-Mag'] ?? null);
    }

    private function categoryForType(string $type): string
    {
        return match (trim($type)) {
            'G', 'GPair', 'GTrpl', 'GGroup' => 'Galaxy',
            'OCl', 'GCl', 'Cl+N', 'As*' => 'Cluster',
            default => 'Nebula',
        };
    }

    private function getSolarSystemTargets(): array
    {
        return [
            ['name' => 'Moon', 'catalog_id' => 'Moon', 'category' => 'Moon', 'distance_rank' => 0, 'apparent_magnitude' => -12.7, 'constellation' => ''],
            ['name' => 'Mercury', 'catalog_id' => 'Mercury', 'category' => 'Planet', 'distance_rank' => 1, 'apparent_magnitude' => -2.5, 'constellation' => ''],
            ['name' => 'Venus', 'catalog_id' => 'Venus', 'category' => 'Planet', 'distance_rank' => 1, 'apparent_magnitude' => -4.9, 'constellation' => ''],
            ['name' => 'Mars', 'catalog_id' => 'Mars', 'category' => 'Planet', 'distance_rank' => 1, 'apparent_magnitude' => -2.9, 'constellation' => ''],
            ['name' => 'Jupiter', 'catalog_id' => 'Jupiter', 'category' => 'Planet', 'distance_rank' => 1, 'apparent_magnitude' => -2.9, 'constellation' => ''],
            ['name' => 'Saturn', 'catalog_id' => 'Saturn', 'category' => 'Planet', 'distance_rank' => 1, 'apparent_magnitude' => 0.7, 'constellation' => ''],
            ['name' => 'Uranus', 'catalog_id' => 'Uranus', 'category' => 'Planet', 'distance_rank' => 1, 'apparent_magnitude' => 5.7, 'constellation' => ''],
            ['name' => 'Neptune', 'catalog_id' => 'Neptune', 'category' => 'Planet', 'distance_rank' => 1, 'apparent_magnitude' => 7.8, 'constellation' => ''],
            ['name' => 'Sun', 'catalog_id' => 'Sun', 'category' => 'Planet', 'distance_rank' => 1, 'apparent_magnitude' => -26.7, 'constellation' => ''],
        ];
    }
}
