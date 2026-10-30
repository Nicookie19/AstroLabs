<?php

namespace App\Console\Commands;

use App\Models\KoiCandidate;
use App\Models\Dataset;
use App\Models\DataSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DataImportKoi extends Command
{
    protected $signature = 'data:import-koi {--force : Force re-import even if data exists}';
    protected $description = 'Import Kepler Objects of Interest from NASA Exoplanet Archive';

    public function handle(): int
    {
        $this->info('Importing KOI data from NASA Exoplanet Archive...');

        $localFile = Storage::path('data/koi.csv');
        $rawFile = Storage::path('raw/koi_cumulative.csv');
        
        $csvData = $this->getCsvData($localFile, $rawFile);
        if (!$csvData) {
            return Command::FAILURE;
        }

        $filtered = array_filter($csvData, function($row) {
            return in_array($row['koi_disposition'] ?? '', ['CONFIRMED', 'FALSE POSITIVE'])
                && !empty($row['koi_depth'])
                && !empty($row['koi_duration'])
                && !empty($row['koi_model_snr']);
        });

        $source = DataSource::updateOrCreate(
            ['key' => 'nasa_exoplanet_archive_koi_cumulative'],
            [
                'title' => 'NASA Exoplanet Archive - Kepler Objects of Interest (Cumulative)',
                'publisher' => 'NASA Exoplanet Archive',
                'url' => 'https://exoplanetarchive.ipac.caltech.edu/TAP/sync?query=select+kepoi_name,koi_disposition,koi_period,koi_duration,koi_depth,koi_model_snr,koi_prad+from+cumulative&format=csv',
                'license' => 'Public Domain (U.S. Government Work)',
                'retrieved_at' => now(),
                'row_count' => count($filtered),
                'citation_text' => 'NASA Exoplanet Archive. (2024). KOI Cumulative Table. Retrieved from https://exoplanetarchive.ipac.caltech.edu/',
            ]
        );

        $dataset = Dataset::updateOrCreate(
            ['mission_key' => 'transit', 'key' => 'kepler_koi'],
            [
                'title' => 'Kepler Objects of Interest',
                'description' => 'KOI dataset for Transit Hunter mission. CONFIRMED = Exoplanet Transit Detected, FALSE POSITIVE = Standard Star.',
                'publisher' => $source->publisher,
                'url' => $source->url,
                'license' => $source->license,
                'row_count' => count($filtered),
                'retrieved_at' => $source->retrieved_at,
                'citation_text' => $source->citation_text,
                'feature_schema' => json_encode([
                    'transit_depth_ppm' => ['unit' => 'ppm', 'min' => 1, 'max' => 10000, 'step' => 1],
                    'signal_to_noise' => ['unit' => '', 'min' => 1, 'max' => 1000, 'step' => 0.1],
                ]),
                'label_names' => json_encode(['Exoplanet Transit Detected', 'Standard Star (no planet signal)']),
                'is_default' => true,
            ]
        );

        KoiCandidate::where('dataset_id', $dataset->id)->delete();

        $batch = [];
        foreach ($filtered as $row) {
            $batch[] = [
                'kepoi_name' => $row['kepoi_name'],
                'disposition' => $row['koi_disposition'],
                'orbital_period_days' => $this->floatVal($row['koi_period']),
                'transit_depth_ppm' => $this->floatVal($row['koi_depth']),
                'transit_duration_hours' => $this->floatVal($row['koi_duration']),
                'signal_to_noise' => $this->floatVal($row['koi_model_snr']),
                'planet_radius_earths' => $this->floatVal($row['koi_prad']),
                'dataset_id' => $dataset->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($batch)) {
            foreach (array_chunk($batch, 500) as $chunk) {
                KoiCandidate::insert($chunk);
            }
        }

        $this->info("Import complete. {$dataset->row_count} rows loaded.");
        return Command::SUCCESS;
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

        $this->info('Downloading from NASA Exoplanet Archive...');
        $url = 'https://exoplanetarchive.ipac.caltech.edu/TAP/sync?query=select+kepoi_name,koi_disposition,koi_period,koi_duration,koi_depth,koi_model_snr,koi_prad+from+cumulative&format=csv';
        
        try {
            $response = Http::timeout(120)->get($url);
            if (!$response->successful()) {
                throw new \Exception("HTTP {$response->status()}: {$response->body()}");
            }
            
            Storage::put('raw/koi_cumulative.csv', $response->body());
            $this->info("Downloaded and saved to storage/app/raw/koi_cumulative.csv");
            return $this->parseCsvFromString($response->body());
        } catch (\Exception $e) {
            $this->error("Download failed: {$e->getMessage()}");
            $this->error("Please manually download the CSV from:");
            $this->error("  https://exoplanetarchive.ipac.caltech.edu/TAP/sync?query=select+kepoi_name,koi_disposition,koi_period,koi_duration,koi_depth,koi_model_snr,koi_prad+from+cumulative&format=csv");
            $this->error("And save it to: storage/app/raw/koi_cumulative.csv");
            return null;
        }
    }

    private function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Could not open file: {$filePath}");
        }

        $header = fgetcsv($handle);
        $data = [];
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) continue;
            $data[] = array_combine($header, $row);
        }
        fclose($handle);
        return $data;
    }

    private function parseCsvFromString(string $content): array
    {
        $lines = explode("\n", trim($content));
        $header = str_getcsv(array_shift($lines));
        $data = [];
        
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $row = str_getcsv($line);
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
}