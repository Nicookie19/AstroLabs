<?php

namespace App\Console\Commands;

use App\Models\Exoplanet;
use App\Models\Dataset;
use App\Models\DataSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DataImportExoplanets extends Command
{
    protected $signature = 'data:import-exoplanets {--force : Force re-import even if data exists}';
    protected $description = 'Import exoplanet data from NASA Exoplanet Archive';

    public function handle(): int
    {
        $this->info('Importing exoplanet data from NASA Exoplanet Archive...');

        $localFile = Storage::path('data/exoplanets.csv');
        $rawFile = Storage::path('raw/pscomppars.csv');
        
        $csvData = $this->getCsvData($localFile, $rawFile);
        if (!$csvData) {
            return Command::FAILURE;
        }

        $source = DataSource::updateOrCreate(
            ['key' => 'nasa_exoplanet_archive_pscomppars'],
            [
                'title' => 'NASA Exoplanet Archive - Planetary Systems Composite Parameters',
                'publisher' => 'NASA Exoplanet Archive',
                'url' => 'https://exoplanetarchive.ipac.caltech.edu/TAP/sync?query=select+pl_name,hostname,pl_orbper,pl_orbsmax,pl_rade,pl_bmasse,pl_dens,pl_eqt,st_mass,disc_year,discoverymethod+from+pscomppars&format=csv',
                'license' => 'Public Domain (U.S. Government Work)',
                'retrieved_at' => now(),
                'row_count' => count($csvData),
                'citation_text' => 'NASA Exoplanet Archive. (2024). Planetary Systems Composite Parameters Table. Retrieved from https://exoplanetarchive.ipac.caltech.edu/',
            ]
        );

        $this->importDatasets($csvData, $source);

        $this->info("Import complete. {$source->row_count} rows loaded.");
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
        $url = 'https://exoplanetarchive.ipac.caltech.edu/TAP/sync?query=select+pl_name,hostname,pl_orbper,pl_orbsmax,pl_rade,pl_bmasse,pl_dens,pl_eqt,st_mass,disc_year,discoverymethod+from+pscomppars&format=csv';
        
        try {
            $response = Http::timeout(120)->get($url);
            if (!$response->successful()) {
                throw new \Exception("HTTP {$response->status()}: {$response->body()}");
            }
            
            Storage::put('raw/pscomppars.csv', $response->body());
            $this->info("Downloaded and saved to storage/app/raw/pscomppars.csv");
            return $this->parseCsvFromString($response->body());
        } catch (\Exception $e) {
            $this->error("Download failed: {$e->getMessage()}");
            $this->error("Please manually download the CSV from:");
            $this->error("  https://exoplanetarchive.ipac.caltech.edu/TAP/sync?query=select+pl_name,hostname,pl_orbper,pl_orbsmax,pl_rade,pl_bmasse,pl_dens,pl_eqt,st_mass,disc_year,discoverymethod+from+pscomppars&format=csv");
            $this->error("And save it to: storage/app/raw/pscomppars.csv");
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
            // Replace ? with empty string
            $row = array_map(fn($val) => $val === '?' ? '' : $val, $row);
            $data[] = array_combine($header, $row);
        }
        fclose($handle);
        return $data;
    }

    private function parseCsvFromString(string $content): array
    {
        // Replace ? with empty string to handle missing values
        $content = str_replace('?', '', $content);
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

    private function importDatasets(array $csvData, DataSource $source): void
    {
        $datasetsConfig = [
            'all_confirmed' => [
                'title' => 'All Confirmed Exoplanets',
                'key' => 'all_confirmed',
                'filter' => fn($row) => true,
                'is_default' => true,
            ],
            'kepler' => [
                'title' => 'Kepler-discovered Planets',
                'key' => 'kepler',
                'filter' => fn($row) => stripos($row['discoverymethod'] ?? '', 'transit') !== false && stripos($row['hostname'] ?? '', 'kepler') !== false,
                'is_default' => false,
            ],
            'tess' => [
                'title' => 'TESS-discovered Planets',
                'key' => 'tess',
                'filter' => fn($row) => stripos($row['hostname'] ?? '', 'tess') !== false || stripos($row['discoverymethod'] ?? '', 'transit') !== false && stripos($row['hostname'] ?? '', 'toi') !== false,
                'is_default' => false,
            ],
            'radial_velocity' => [
                'title' => 'Radial Velocity Discoveries',
                'key' => 'radial_velocity',
                'filter' => fn($row) => stripos($row['discoverymethod'] ?? '', 'radial velocity') !== false,
                'is_default' => false,
            ],
            'confirmed_exoplanets_knn' => [
                'title' => 'Confirmed Exoplanets',
                'key' => 'confirmed_exoplanets',
                'mission_key' => 'exoplanets',
                'description' => 'Confirmed exoplanets for the Twin Finder. Planet type is derived from mass; temperature, radius, and bulk density are the KNN features.',
                'filter' => fn($row) => !empty($row['pl_rade']) && !empty($row['pl_bmasse'])
                    && !empty($row['pl_dens']) && !empty($row['pl_eqt']),
                'is_default' => true,
            ],
            'solar_system' => [
                'title' => 'Our Solar System',
                'key' => 'solar_system',
                'filter' => fn($row) => false,
                'is_default' => false,
                'custom_data' => $this->getSolarSystemData(),
            ],
        ];

        foreach ($datasetsConfig as $config) {
            $filteredData = $config['custom_data'] ?? array_filter($csvData, $config['filter']);
            
            $validRows = array_filter($filteredData, function($row) {
                return !empty($row['pl_name']) && !empty($row['hostname']);
            });

            $missionKey = $config['mission_key'] ?? 'orbit';
            $dataset = Dataset::updateOrCreate(
                ['mission_key' => $missionKey, 'key' => $config['key']],
                [
                    'title' => $config['title'],
                    'description' => $config['description'] ?? 'Exoplanet dataset for Orbit Calculator mission.',
                    'publisher' => $source->publisher,
                    'url' => $source->url,
                    'license' => $source->license,
                    'row_count' => count($validRows),
                    'retrieved_at' => $source->retrieved_at,
                    'citation_text' => $source->citation_text,
                    'feature_schema' => json_encode([
                        'semi_major_axis_au' => ['unit' => 'AU', 'min' => 0.01, 'max' => 100, 'step' => 0.01],
                        'star_mass_solar' => ['unit' => 'Solar masses', 'min' => 0.08, 'max' => 10, 'step' => 0.01],
                    ]),
                    'label_names' => null,
                    'is_default' => $config['is_default'],
                ]
            );

            Exoplanet::where('dataset_id', $dataset->id)->delete();

            $batch = [];
            foreach ($validRows as $row) {
                $batch[] = [
                    'name' => $row['pl_name'],
                    'host_star' => $row['hostname'],
                    'orbital_period_days' => $this->floatVal($row['pl_orbper']),
                    'semi_major_axis_au' => $this->floatVal($row['pl_orbsmax']),
                    'star_mass_solar' => $this->floatVal($row['st_mass']),
                    'radius_earths' => $this->floatVal($row['pl_rade']),
                    'mass_earths' => $this->floatVal($row['pl_bmasse']),
                    'bulk_density_g_cm3' => $this->floatVal($row['pl_dens']),
                    'equilibrium_temp_k' => $this->floatVal($row['pl_eqt']),
                    'discovery_year' => $this->intVal($row['disc_year']),
                    'discovery_method' => $row['discoverymethod'] ?? null,
                    'dataset_id' => $dataset->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($batch)) {
                foreach (array_chunk($batch, 500) as $chunk) {
                    \DB::table('exoplanets')->insert($chunk);
                }
            }

            $this->info("  Dataset '{$config['title']}': " . count($validRows) . " rows imported.");
        }
    }

    private function getSolarSystemData(): array
    {
        return [
            ['pl_name' => 'Mercury', 'hostname' => 'Sun', 'pl_orbper' => 87.97, 'pl_orbsmax' => 0.387, 'st_mass' => 1.0, 'pl_rade' => 0.383, 'pl_bmasse' => 0.055, 'pl_dens' => 5.43, 'pl_eqt' => 440, 'disc_year' => -1000, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Venus', 'hostname' => 'Sun', 'pl_orbper' => 224.70, 'pl_orbsmax' => 0.723, 'st_mass' => 1.0, 'pl_rade' => 0.949, 'pl_bmasse' => 0.815, 'pl_dens' => 5.24, 'pl_eqt' => 232, 'disc_year' => -1000, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Earth', 'hostname' => 'Sun', 'pl_orbper' => 365.25, 'pl_orbsmax' => 1.000, 'st_mass' => 1.0, 'pl_rade' => 1.000, 'pl_bmasse' => 1.000, 'pl_dens' => 5.51, 'pl_eqt' => 255, 'disc_year' => -1000, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Mars', 'hostname' => 'Sun', 'pl_orbper' => 686.98, 'pl_orbsmax' => 1.524, 'st_mass' => 1.0, 'pl_rade' => 0.532, 'pl_bmasse' => 0.107, 'pl_dens' => 3.93, 'pl_eqt' => 210, 'disc_year' => -1000, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Jupiter', 'hostname' => 'Sun', 'pl_orbper' => 4332.59, 'pl_orbsmax' => 5.203, 'st_mass' => 1.0, 'pl_rade' => 11.209, 'pl_bmasse' => 317.8, 'pl_dens' => 1.33, 'pl_eqt' => 110, 'disc_year' => -1000, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Saturn', 'hostname' => 'Sun', 'pl_orbper' => 10759.22, 'pl_orbsmax' => 9.537, 'st_mass' => 1.0, 'pl_rade' => 9.449, 'pl_bmasse' => 95.2, 'pl_dens' => 0.69, 'pl_eqt' => 81, 'disc_year' => -1000, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Uranus', 'hostname' => 'Sun', 'pl_orbper' => 30688.5, 'pl_orbsmax' => 19.191, 'st_mass' => 1.0, 'pl_rade' => 4.007, 'pl_bmasse' => 14.5, 'pl_dens' => 1.27, 'pl_eqt' => 58, 'disc_year' => 1781, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Neptune', 'hostname' => 'Sun', 'pl_orbper' => 60182, 'pl_orbsmax' => 30.069, 'st_mass' => 1.0, 'pl_rade' => 3.883, 'pl_bmasse' => 17.1, 'pl_dens' => 1.64, 'pl_eqt' => 47, 'disc_year' => 1846, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Ceres', 'hostname' => 'Sun', 'pl_orbper' => 1681.6, 'pl_orbsmax' => 2.767, 'st_mass' => 1.0, 'pl_rade' => 0.084, 'pl_bmasse' => 0.00015, 'pl_dens' => 2.16, 'pl_eqt' => 168, 'disc_year' => 1801, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Pluto', 'hostname' => 'Sun', 'pl_orbper' => 90560, 'pl_orbsmax' => 39.482, 'st_mass' => 1.0, 'pl_rade' => 0.186, 'pl_bmasse' => 0.00218, 'pl_dens' => 1.86, 'pl_eqt' => 44, 'disc_year' => 1930, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Haumea', 'hostname' => 'Sun', 'pl_orbper' => 103774, 'pl_orbsmax' => 43.13, 'st_mass' => 1.0, 'pl_rade' => 0.136, 'pl_bmasse' => 0.00067, 'pl_dens' => 2.01, 'pl_eqt' => 42, 'disc_year' => 2004, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Makemake', 'hostname' => 'Sun', 'pl_orbper' => 111845, 'pl_orbsmax' => 45.79, 'st_mass' => 1.0, 'pl_rade' => 0.126, 'pl_bmasse' => 0.00067, 'pl_dens' => 1.7, 'pl_eqt' => 41, 'disc_year' => 2005, 'discoverymethod' => 'Historical'],
            ['pl_name' => 'Eris', 'hostname' => 'Sun', 'pl_orbper' => 203830, 'pl_orbsmax' => 67.78, 'st_mass' => 1.0, 'pl_rade' => 0.187, 'pl_bmasse' => 0.0028, 'pl_dens' => 2.5, 'pl_eqt' => 30, 'disc_year' => 2005, 'discoverymethod' => 'Historical'],
        ];
    }

    private function floatVal($val): ?float
    {
        if ($val === '' || $val === null || $val === '?') return null;
        $f = (float)$val;
        if (is_nan($f) || is_infinite($f)) return null;
        // Round to 4 decimal places to fit decimal(8,4)
        return round($f, 4);
    }

    private function intVal($val): ?int
    {
        if ($val === '' || $val === null || $val === '?') return null;
        return (int)$val;
    }
}
