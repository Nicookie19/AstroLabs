<?php

namespace App\Services\ML;

use App\Models\KoiCandidate;
use App\Models\MlModel;
use App\Models\Dataset;
use Illuminate\Support\Facades\Log;

class SvmService
{
    public function train(Dataset $dataset): MlModel
    {
        $candidates = KoiCandidate::where('dataset_id', $dataset->id)
            ->whereIn('disposition', ['CONFIRMED', 'FALSE POSITIVE'])
            ->whereNotNull('transit_depth_ppm')
            ->whereNotNull('transit_duration_hours')
            ->whereNotNull('signal_to_noise')
            ->where('transit_depth_ppm', '>', 0)
            ->where('signal_to_noise', '>', 0)
            ->get(['disposition', 'transit_depth_ppm', 'transit_duration_hours', 'signal_to_noise'])
            ->toArray();

        if (count($candidates) < 50) {
            throw new \Exception('Insufficient data for training. Need at least 50 samples.');
        }

        $confirmed = array_filter($candidates, fn($c) => $c['disposition'] === 'CONFIRMED');
        $falsePos = array_filter($candidates, fn($c) => $c['disposition'] === 'FALSE POSITIVE');

        $minCount = min(count($confirmed), count($falsePos), 1000);
        
        $balanced = array_merge(
            array_slice($confirmed, 0, $minCount),
            array_slice($falsePos, 0, $minCount)
        );

        shuffle($balanced);

        $n = count($balanced);
        $split = (int)($n * 0.8);
        $trainData = array_slice($balanced, 0, $split);
        $testData = array_slice($balanced, $split);

        $features = $this->extractFeatures($trainData);
        
        $means = [];
        $stds = [];
        foreach (['log_depth', 'log_snr'] as $feat) {
            $vals = array_column($features, $feat);
            $means[$feat] = array_sum($vals) / count($vals);
            $stds[$feat] = sqrt(array_sum(array_map(fn($v) => pow($v - $means[$feat], 2), $vals)) / count($vals));
            if ($stds[$feat] < 1e-10) $stds[$feat] = 1;
        }

        $scaledTrain = $this->scaleFeatures($features, $means, $stds);
        
        $lambda = 0.01;
        $weights = $this->trainSgd($scaledTrain, $lambda, 300, 0.05);
        
        $testFeatures = $this->extractFeatures($testData);
        $scaledTest = $this->scaleFeatures($testFeatures, $means, $stds);
        $accuracy = $this->evaluate($scaledTest, $weights);

        $parameters = [
            'weights' => $weights,
            'means' => $means,
            'stds' => $stds,
            'lambda' => $lambda,
            'feature_names' => ['log10_transit_depth_ppm', 'log10_signal_to_noise'],
            'label_mapping' => [
                'CONFIRMED' => 'Exoplanet Transit Detected',
                'FALSE POSITIVE' => 'Standard Star (no planet signal)',
            ],
        ];

        $metrics = [
            'test_accuracy' => $accuracy,
            'train_samples' => count($trainData),
            'test_samples' => count($testData),
            'lambda' => $lambda,
        ];

        return MlModel::create([
            'name' => 'svm_transit_' . $dataset->key,
            'parameters' => $parameters,
            'metrics' => $metrics,
            'dataset_id' => $dataset->id,
            'trained_at' => now(),
        ]);
    }

    private function extractFeatures(array $data): array
    {
        return array_map(function($row) {
            return [
                'log_depth' => log10($row['transit_depth_ppm']),
                'log_snr' => log10($row['signal_to_noise']),
                'label' => $row['disposition'] === 'CONFIRMED' ? 1 : -1,
                'raw' => $row,
            ];
        }, $data);
    }

    private function scaleFeatures(array $features, array $means, array $stds): array
    {
        return array_map(function($f) use ($means, $stds) {
            return [
                'log_depth' => ($f['log_depth'] - $means['log_depth']) / $stds['log_depth'],
                'log_snr' => ($f['log_snr'] - $means['log_snr']) / $stds['log_snr'],
                'label' => $f['label'],
                'raw' => $f['raw'],
            ];
        }, $features);
    }

    private function trainSgd(array $data, float $lambda, int $epochs, float $lr): array
    {
        $w = [0, 0];
        $b = 0;
        $n = count($data);

        for ($epoch = 0; $epoch < $epochs; $epoch++) {
            $indices = range(0, $n - 1);
            shuffle($indices);

            foreach ($indices as $i) {
                $x = [$data[$i]['log_depth'], $data[$i]['log_snr']];
                $y = $data[$i]['label'];
                
                $margin = $y * ($w[0] * $x[0] + $w[1] * $x[1] + $b);
                
                if ($margin < 1) {
                    $w[0] = $w[0] - $lr * ($lambda * $w[0] - $y * $x[0]);
                    $w[1] = $w[1] - $lr * ($lambda * $w[1] - $y * $x[1]);
                    $b = $b + $lr * $y;
                } else {
                    $w[0] = $w[0] - $lr * $lambda * $w[0];
                    $w[1] = $w[1] - $lr * $lambda * $w[1];
                }
            }
        }

        return ['w0' => $w[0], 'w1' => $w[1], 'b' => $b];
    }

    private function evaluate(array $data, array $weights): float
    {
        $correct = 0;
        foreach ($data as $i => $x) {
            $score = $weights['w0'] * $x['log_depth'] + $weights['w1'] * $x['log_snr'] + $weights['b'];
            $pred = $score >= 0 ? 1 : -1;
            if ($pred === $x['label']) $correct++;
        }
        return count($data) > 0 ? $correct / count($data) : 0;
    }

    public function classify(float $depthPpm, float $snr, float $lambda, MlModel $model): array
    {
        $parameters = $model->parameters;
        $means = $parameters['means'];
        $stds = $parameters['stds'];
        $weights = $parameters['weights'];

        $logDepth = log10($depthPpm);
        $logSnr = log10($snr);

        $scaledDepth = ($logDepth - $means['log_depth']) / $stds['log_depth'];
        $scaledSnr = ($logSnr - $means['log_snr']) / $stds['log_snr'];

        $score = $weights['w0'] * $scaledDepth + $weights['w1'] * $scaledSnr + $weights['b'];
        $label = $score >= 0 ? 1 : -1;
        $labelText = $label === 1 ? 'Exoplanet Transit Detected' : 'Standard Star (no planet signal)';

        $margin = abs($score);
        $distanceFromBoundary = $margin / sqrt($weights['w0']**2 + $weights['w1']**2);

        $explanation = [
            'input_features' => [
                'transit_depth_ppm' => $depthPpm,
                'signal_to_noise' => $snr,
                'log10_depth' => round($logDepth, 4),
                'log10_snr' => round($logSnr, 4),
            ],
            'standardized' => [
                'depth' => round($scaledDepth, 4),
                'snr' => round($scaledSnr, 4),
            ],
            'decision_function' => sprintf(
                'f(x) = %.4f·x₁ + %.4f·x₂ + %.4f',
                $weights['w0'], $weights['w1'], $weights['b']
            ),
            'score' => round($score, 4),
            'margin' => round($margin, 4),
            'distance_from_boundary' => round($distanceFromBoundary, 4),
            'lambda' => $lambda,
            'label_mapping' => $parameters['label_mapping'],
            'model_accuracy' => $model->metrics['test_accuracy'] ?? null,
            'support_vectors_note' => 'Support vectors are training points closest to the decision boundary (margin = 1). They define the classifier. In SGD training, all points with margin < 1 act as support vectors during updates.',
        ];

        return [
            'label' => $labelText,
            'raw_label' => $label,
            'score' => round($score, 4),
            'distance_from_boundary' => round($distanceFromBoundary, 4),
            'explanation' => $explanation,
        ];
    }

    public function getDecisionBoundaryData(Dataset $dataset, MlModel $model, int $points = 300): array
    {
        $candidates = KoiCandidate::where('dataset_id', $dataset->id)
            ->whereIn('disposition', ['CONFIRMED', 'FALSE POSITIVE'])
            ->whereNotNull('transit_depth_ppm')
            ->whereNotNull('transit_duration_hours')
            ->whereNotNull('signal_to_noise')
            ->where('transit_depth_ppm', '>', 0)
            ->where('signal_to_noise', '>', 0)
            ->inRandomOrder()
            ->limit($points)
            ->get(['disposition', 'transit_depth_ppm', 'transit_duration_hours', 'signal_to_noise'])
            ->toArray();

        $parameters = $model->parameters;
        $means = $parameters['means'];
        $stds = $parameters['stds'];
        $weights = $parameters['weights'];

        $dataPoints = [];
        foreach ($candidates as $c) {
            $logDepth = log10($c['transit_depth_ppm']);
            $logSnr = log10($c['signal_to_noise']);
            $scaledDepth = ($logDepth - $means['log_depth']) / $stds['log_depth'];
            $scaledSnr = ($logSnr - $means['log_snr']) / $stds['log_snr'];
            $score = $weights['w0'] * $scaledDepth + $weights['w1'] * $scaledSnr + $weights['b'];
            
            $dataPoints[] = [
                'depth' => $c['transit_depth_ppm'],
                'snr' => $c['signal_to_noise'],
                'log_depth' => $logDepth,
                'log_snr' => $logSnr,
                'scaled_depth' => $scaledDepth,
                'scaled_snr' => $scaledSnr,
                'score' => $score,
                'label' => $c['disposition'] === 'CONFIRMED' ? 'Exoplanet Transit Detected' : 'Standard Star (no planet signal)',
                'raw_label' => $c['disposition'],
                'duration_hours' => $c['transit_duration_hours'],
            ];
        }

        $xRange = [min(array_column($dataPoints, 'scaled_depth')), max(array_column($dataPoints, 'scaled_depth'))];
        $yRange = [min(array_column($dataPoints, 'scaled_snr')), max(array_column($dataPoints, 'scaled_snr'))];
        
        $margin = 1 / sqrt($weights['w0']**2 + $weights['w1']**2);
        
        $boundary = [];
        for ($x = $xRange[0]; $x <= $xRange[1]; $x += ($xRange[1] - $xRange[0]) / 50) {
            $y = -($weights['w0'] * $x + $weights['b']) / $weights['w1'];
            if ($y >= $yRange[0] && $y <= $yRange[1]) {
                $boundary[] = ['x' => $x, 'y' => $y];
            }
        }

        $marginPos = [];
        $marginNeg = [];
        for ($x = $xRange[0]; $x <= $xRange[1]; $x += ($xRange[1] - $xRange[0]) / 50) {
            $yPos = -($weights['w0'] * $x + $weights['b'] - 1) / $weights['w1'];
            $yNeg = -($weights['w0'] * $x + $weights['b'] + 1) / $weights['w1'];
            if ($yPos >= $yRange[0] && $yPos <= $yRange[1]) $marginPos[] = ['x' => $x, 'y' => $yPos];
            if ($yNeg >= $yRange[0] && $yNeg <= $yRange[1]) $marginNeg[] = ['x' => $x, 'y' => $yNeg];
        }

        return [
            'points' => $dataPoints,
            'boundary' => $boundary,
            'margin_positive' => $marginPos,
            'margin_negative' => $marginNeg,
            'weights' => $weights,
        ];
    }

    public function generateLightCurve(float $depthPpm, float $durationHours): array
    {
        $time = [];
        $flux = [];
        $duration = $durationHours / 24;
        
        for ($i = 0; $i <= 200; $i++) {
            $t = -2 * $duration + $i * 4 * $duration / 200;
            $time[] = $t;
            
            if (abs($t) < $duration / 2) {
                $flux[] = 1 - ($depthPpm / 1e6);
            } else {
                $flux[] = 1 + (mt_rand(-50, 50) / 1e6);
            }
        }

        return [
            'time' => $time,
            'flux' => $flux,
            'note' => 'Reconstructed from catalog values (depth, duration), not raw telescope data. Ingress/egress simplified to box model.',
            'transit_depth_ppm' => $depthPpm,
            'transit_duration_hours' => $durationHours,
        ];
    }
}
