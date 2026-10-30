<?php

namespace App\Services\ML;

use App\Models\Exoplanet;
use App\Models\MlModel;
use App\Models\Dataset;
use Illuminate\Support\Facades\Log;

class KnnService
{
    public function train(Dataset $dataset): MlModel
    {
        $exoplanets = Exoplanet::where('dataset_id', $dataset->id)
            ->whereNotNull('radius_earths')
            ->whereNotNull('bulk_density_g_cm3')
            ->whereNotNull('equilibrium_temp_k')
            ->whereNotNull('mass_earths')
            ->where('radius_earths', '>', 0)
            ->where('bulk_density_g_cm3', '>', 0)
            ->where('equilibrium_temp_k', '>', 0)
            ->where('mass_earths', '>', 0)
            ->get(['name', 'host_star', 'radius_earths', 'bulk_density_g_cm3', 'equilibrium_temp_k', 'mass_earths', 'discovery_year'])
            ->toArray();

        if (count($exoplanets) < 20) {
            throw new \Exception('Insufficient data for training. Need at least 20 samples.');
        }

        $processed = [];
        foreach ($exoplanets as $exo) {
            $type = $this->derivePlanetType($exo['mass_earths']);
            $processed[] = [
                'name' => $exo['name'],
                'host_star' => $exo['host_star'],
                'discovery_year' => $exo['discovery_year'],
                'features' => [
                    'ln_radius' => log($exo['radius_earths']),
                    'ln_density' => log($exo['bulk_density_g_cm3']),
                    'eq_temp' => $exo['equilibrium_temp_k'],
                ],
                'type' => $type,
                'raw' => $exo,
            ];
        }

        $n = count($processed);
        $split = (int)($n * 0.8);
        shuffle($processed);
        $trainData = array_slice($processed, 0, $split);
        $testData = array_slice($processed, $split);

        $featureNames = ['ln_radius', 'ln_density', 'eq_temp'];
        $means = [];
        $stds = [];

        foreach ($featureNames as $feat) {
            $vals = array_map(fn($row) => $row['features'][$feat], $trainData);
            $means[$feat] = array_sum($vals) / count($vals);
            $stds[$feat] = sqrt(array_sum(array_map(fn($v) => pow($v - $means[$feat], 2), $vals)) / count($vals));
            if ($stds[$feat] < 1e-10) $stds[$feat] = 1;
        }

        $scaledTrain = $this->scaleFeatures($trainData, $featureNames, $means, $stds);
        $scaledTest = $this->scaleFeatures($testData, $featureNames, $means, $stds);

        $accuracies = [];
        for ($k = 1; $k <= 9; $k += 2) {
            $correct = 0;
            foreach ($scaledTest as $test) {
                $pred = $this->predictSingle($test['scaled_features'], $scaledTrain, $k);
                if ($pred === $test['type']) $correct++;
            }
            $accuracies[$k] = count($scaledTest) > 0 ? $correct / count($scaledTest) : 0;
        }

        $parameters = [
            'train_data' => $scaledTrain,
            'feature_names' => $featureNames,
            'means' => $means,
            'stds' => $stds,
            'type_mapping' => [
                'Terrestrial/Super-Earth' => 'Under 10 Earth masses',
                'Neptune-like' => '10-50 Earth masses',
                'Gas giant' => 'Over 50 Earth masses',
            ],
        ];

        $metrics = [
            'accuracies_per_k' => $accuracies,
            'train_samples' => count($trainData),
            'test_samples' => count($testData),
            'note' => 'Planet type derived from mass only (not used as feature). Features: equilibrium temperature, radius, bulk density.',
        ];

        return MlModel::create([
            'name' => 'knn_exoplanet_' . $dataset->key,
            'parameters' => $parameters,
            'metrics' => $metrics,
            'dataset_id' => $dataset->id,
            'trained_at' => now(),
        ]);
    }

    private function derivePlanetType(float $mass): string
    {
        if ($mass < 10) return 'Terrestrial/Super-Earth';
        if ($mass < 50) return 'Neptune-like';
        return 'Gas giant';
    }

    private function scaleFeatures(array $data, array $featureNames, array $means, array $stds): array
    {
        return array_map(function($row) use ($featureNames, $means, $stds) {
            $scaled = [];
            foreach ($featureNames as $feat) {
                $scaled[$feat] = ($row['features'][$feat] - $means[$feat]) / $stds[$feat];
            }
            return array_merge($row, ['scaled_features' => $scaled]);
        }, $data);
    }

    private function predictSingle(array $features, array $trainData, int $k): string
    {
        $distances = [];
        foreach ($trainData as $train) {
            $dist = 0;
            foreach ($features as $feat => $val) {
                $diff = $val - $train['scaled_features'][$feat];
                $dist += $diff * $diff;
            }
            $distances[] = ['dist' => sqrt($dist), 'type' => $train['type'], 'train' => $train];
        }

        usort($distances, fn($a, $b) => $a['dist'] <=> $b['dist']);
        $neighbors = array_slice($distances, 0, $k);

        $votes = [];
        foreach ($neighbors as $n) {
            $votes[$n['type']] = ($votes[$n['type']] ?? 0) + 1;
        }

        arsort($votes);
        return array_key_first($votes);
    }

    public function findNeighbors(array $features, MlModel $model, int $k): array
    {
        $parameters = $model->parameters;
        $trainData = $parameters['train_data'];
        $featureNames = $parameters['feature_names'];
        $means = $parameters['means'];
        $stds = $parameters['stds'];

        $scaledFeatures = [];
        foreach ($featureNames as $feat) {
            $scaledFeatures[$feat] = ($features[$feat] - $means[$feat]) / $stds[$feat];
        }

        $distances = [];
        foreach ($trainData as $train) {
            $dist = 0;
            $featDiffs = [];
            foreach ($featureNames as $feat) {
                $diff = $scaledFeatures[$feat] - $train['scaled_features'][$feat];
                $featDiffs[$feat] = $diff;
                $dist += $diff * $diff;
            }
            $distances[] = [
                'dist' => sqrt($dist),
                'type' => $train['type'],
                'name' => $train['name'],
                'host_star' => $train['host_star'],
                'discovery_year' => $train['discovery_year'],
                'feature_diffs' => $featDiffs,
                'raw_features' => $train['features'],
            ];
        }

        usort($distances, fn($a, $b) => $a['dist'] <=> $b['dist']);
        $neighbors = array_slice($distances, 0, $k);

        $votes = [];
        foreach ($neighbors as $n) {
            $votes[$n['type']] = ($votes[$n['type']] ?? 0) + 1;
        }
        arsort($votes);
        $prediction = array_key_first($votes);

        $explanation = [
            'standardized_input' => $scaledFeatures,
            'neighbors' => array_map(function($n) use ($featureNames) {
                return [
                    'name' => $n['name'],
                    'host_star' => $n['host_star'],
                    'discovery_year' => $n['discovery_year'],
                    'distance' => round($n['dist'], 4),
                    'type' => $n['type'],
                    'feature_differences' => $n['feature_diffs'],
                    'raw_features' => $n['raw_features'],
                ];
            }, $neighbors),
            'vote_counts' => $votes,
            'prediction' => $prediction,
            'k' => $k,
            'held_out_accuracy' => $model->metrics['accuracies_per_k'][$k] ?? null,
            'all_k_accuracies' => $model->metrics['accuracies_per_k'] ?? [],
        ];

        return [
            'prediction' => $prediction,
            'neighbors' => $neighbors,
            'explanation' => $explanation,
        ];
    }

    public function getPresets(): array
    {
        return [
            'Earth-like' => ['eq_temp' => 288, 'radius_earths' => 1, 'bulk_density_g_cm3' => 5.51],
            'Hot Jupiter' => ['eq_temp' => 1500, 'radius_earths' => 11, 'bulk_density_g_cm3' => 1.3],
            'Sub-Neptune' => ['eq_temp' => 600, 'radius_earths' => 2.5, 'bulk_density_g_cm3' => 3.0],
        ];
    }
}
