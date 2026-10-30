<?php

namespace App\Services\ML;

use App\Models\GalaxyImage;
use App\Models\MlModel;
use App\Models\Dataset;
use Illuminate\Support\Facades\Log;

class NeuralNetworkService
{
    private int $inputSize = 256;
    private int $hiddenSize = 24;
    private int $outputSize = 3;
    private float $learningRate = 0.01;
    private int $epochs = 50;

    public function train(Dataset $dataset): MlModel
    {
        $labelNames = $this->labelNames($dataset);
        
        $trainImages = GalaxyImage::where('dataset_id', $dataset->id)
            ->where('split', 'train')
            ->whereIn('label', $labelNames)
            ->get(['pixels', 'label'])
            ->toArray();

        $testImages = GalaxyImage::where('dataset_id', $dataset->id)
            ->where('split', 'test')
            ->whereIn('label', $labelNames)
            ->get(['pixels', 'label'])
            ->toArray();

        if (count($trainImages) < 50 || count($testImages) < 10) {
            throw new \Exception('Insufficient data for training. Need at least 50 train and 10 test samples.');
        }

        $labelToIndex = array_flip($labelNames);
        
        $trainData = array_map(function($img) use ($labelToIndex) {
            return [
                'input' => $img['pixels'],
                'target' => $labelToIndex[$img['label']],
            ];
        }, $trainImages);

        $testData = array_map(function($img) use ($labelToIndex) {
            return [
                'input' => $img['pixels'],
                'target' => $labelToIndex[$img['label']],
            ];
        }, $testImages);

        $weights = $this->initializeWeights();
        
        Log::info("Starting neural network training for {$dataset->key}", [
            'train_samples' => count($trainData),
            'test_samples' => count($testData),
            'epochs' => $this->epochs,
        ]);

        $weights = $this->trainNetwork($weights, $trainData, $labelNames);

        $metrics = $this->evaluate($weights, $testData, $labelNames);

        $parameters = [
            'weights_ih' => $weights['ih'],
            'weights_ho' => $weights['ho'],
            'bias_h' => $weights['bh'],
            'bias_o' => $weights['bo'],
            'label_names' => $labelNames,
            'architecture' => [
                'input' => $this->inputSize,
                'hidden' => $this->hiddenSize,
                'output' => $this->outputSize,
            ],
            'hyperparameters' => [
                'learning_rate' => $this->learningRate,
                'epochs' => $this->epochs,
                'batch_size' => 1,
            ],
        ];

        return MlModel::create([
            'name' => 'neural_network_galaxy_' . $dataset->key,
            'parameters' => $parameters,
            'metrics' => $metrics,
            'dataset_id' => $dataset->id,
            'trained_at' => now(),
        ]);
    }

    private function initializeWeights(): array
    {
        mt_srand(42);
        
        $ih = [];
        for ($i = 0; $i < $this->inputSize; $i++) {
            for ($h = 0; $h < $this->hiddenSize; $h++) {
                $ih[$i][$h] = (mt_rand() / mt_getrandmax() - 0.5) * 2 * sqrt(6 / ($this->inputSize + $this->hiddenSize));
            }
        }

        $ho = [];
        for ($h = 0; $h < $this->hiddenSize; $h++) {
            for ($o = 0; $o < $this->outputSize; $o++) {
                $ho[$h][$o] = (mt_rand() / mt_getrandmax() - 0.5) * 2 * sqrt(6 / ($this->hiddenSize + $this->outputSize));
            }
        }

        $bh = array_fill(0, $this->hiddenSize, 0);
        $bo = array_fill(0, $this->outputSize, 0);

        return ['ih' => $ih, 'ho' => $ho, 'bh' => $bh, 'bo' => $bo];
    }

    private function forward(array $weights, array $input): array
    {
        $hidden = [];
        for ($h = 0; $h < $this->hiddenSize; $h++) {
            $sum = $weights['bh'][$h];
            for ($i = 0; $i < $this->inputSize; $i++) {
                $sum += $input[$i] * $weights['ih'][$i][$h];
            }
            $hidden[$h] = tanh($sum);
        }

        $output = [];
        for ($o = 0; $o < $this->outputSize; $o++) {
            $sum = $weights['bo'][$o];
            for ($h = 0; $h < $this->hiddenSize; $h++) {
                $sum += $hidden[$h] * $weights['ho'][$h][$o];
            }
            $output[$o] = $sum;
        }

        $maxOut = max($output);
        $expOut = array_map(fn($v) => exp($v - $maxOut), $output);
        $sumExp = array_sum($expOut);
        $probs = array_map(fn($v) => $v / $sumExp, $expOut);

        return ['hidden' => $hidden, 'output' => $output, 'probs' => $probs];
    }

    private function trainNetwork(array $weights, array $trainData, array $labelNames): array
    {
        $n = count($trainData);
        
        for ($epoch = 0; $epoch < $this->epochs; $epoch++) {
            $indices = range(0, $n - 1);
            shuffle($indices);

            $totalLoss = 0;
            
            foreach ($indices as $idx) {
                $sample = $trainData[$idx];
                $input = $sample['input'];
                $target = $sample['target'];

                $forward = $this->forward($weights, $input);
                $hidden = $forward['hidden'];
                $probs = $forward['probs'];

                $targetVec = array_fill(0, $this->outputSize, 0);
                $targetVec[$target] = 1;

                $outputErrors = [];
                for ($o = 0; $o < $this->outputSize; $o++) {
                    $outputErrors[$o] = $probs[$o] - $targetVec[$o];
                    $totalLoss += -$targetVec[$o] * log(max($probs[$o], 1e-15));
                }

                $hiddenErrors = array_fill(0, $this->hiddenSize, 0);
                for ($h = 0; $h < $this->hiddenSize; $h++) {
                    for ($o = 0; $o < $this->outputSize; $o++) {
                        $hiddenErrors[$h] += $outputErrors[$o] * $weights['ho'][$h][$o];
                    }
                    $hiddenErrors[$h] *= (1 - $hidden[$h] * $hidden[$h]);
                }

                for ($h = 0; $h < $this->hiddenSize; $h++) {
                    for ($o = 0; $o < $this->outputSize; $o++) {
                        $weights['ho'][$h][$o] -= $this->learningRate * $outputErrors[$o] * $hidden[$h];
                    }
                }
                for ($o = 0; $o < $this->outputSize; $o++) {
                    $weights['bo'][$o] -= $this->learningRate * $outputErrors[$o];
                }

                for ($i = 0; $i < $this->inputSize; $i++) {
                    for ($h = 0; $h < $this->hiddenSize; $h++) {
                        $weights['ih'][$i][$h] -= $this->learningRate * $hiddenErrors[$h] * $input[$i];
                    }
                }
                for ($h = 0; $h < $this->hiddenSize; $h++) {
                    $weights['bh'][$h] -= $this->learningRate * $hiddenErrors[$h];
                }
            }

            if ($epoch % 10 === 0) {
                Log::info("Epoch {$epoch} loss: " . round($totalLoss / $n, 4));
            }
        }

        return $weights;
    }

    private function evaluate(array $weights, array $testData, array $labelNames): array
    {
        $confusionMatrix = [];
        foreach ($labelNames as $ln) {
            $confusionMatrix[$ln] = array_fill_keys($labelNames, 0);
        }

        $correct = 0;
        $classCorrect = array_fill_keys($labelNames, 0);
        $classTotal = array_fill_keys($labelNames, 0);

        foreach ($testData as $sample) {
            $forward = $this->forward($weights, $sample['input']);
            $predIdx = array_keys($forward['probs'], max($forward['probs']))[0];
            $trueLabel = $labelNames[$sample['target']];
            $predLabel = $labelNames[$predIdx];

            $confusionMatrix[$trueLabel][$predLabel]++;
            $classTotal[$trueLabel]++;
            if ($trueLabel === $predLabel) {
                $correct++;
                $classCorrect[$trueLabel]++;
            }
        }

        $perClassAccuracy = [];
        foreach ($labelNames as $ln) {
            $perClassAccuracy[$ln] = $classTotal[$ln] > 0 ? $classCorrect[$ln] / $classTotal[$ln] : 0;
        }

        return [
            'test_accuracy' => count($testData) > 0 ? $correct / count($testData) : 0,
            'confusion_matrix' => $confusionMatrix,
            'per_class_accuracy' => $perClassAccuracy,
            'test_samples' => count($testData),
        ];
    }

    public function predict(array $pixels, MlModel $model): array
    {
        $parameters = $model->parameters;
        $weights = [
            'ih' => $parameters['weights_ih'],
            'ho' => $parameters['weights_ho'],
            'bh' => $parameters['bias_h'],
            'bo' => $parameters['bias_o'],
        ];
        $labelNames = $parameters['label_names'];

        $forward = $this->forward($weights, $pixels);
        $probs = $forward['probs'];
        $hidden = $forward['hidden'];

        $predIdx = array_keys($probs, max($probs))[0];
        $prediction = $labelNames[$predIdx];

        $probArray = [];
        foreach ($labelNames as $i => $name) {
            $probArray[$name] = round($probs[$i] * 100, 1);
        }

        $heatmap = $this->computePixelImportance($weights, $hidden, $predIdx);

        $explanation = [
            'architecture' => $parameters['architecture'],
            'input_preview' => array_slice($pixels, 0, 16),
            'hidden_activations' => array_map(fn($v) => round($v, 4), $hidden),
            'output_logits' => array_map(fn($v) => round($v, 4), $forward['output']),
            'probabilities' => $probArray,
            'prediction' => $prediction,
            'pixel_importance_heatmap' => $heatmap,
            'note' => 'Network sees 16×16 grayscale (256 inputs) → 24 tanh hidden → 3 softmax outputs. Tiny network on tiny images makes mistakes; see confusion matrix.',
        ];

        return [
            'prediction' => $prediction,
            'probabilities' => $probArray,
            'explanation' => $explanation,
        ];
    }

    private function computePixelImportance(array $weights, array $hidden, int $winningClass): array
    {
        $importance = array_fill(0, $this->inputSize, 0);
        
        for ($i = 0; $i < $this->inputSize; $i++) {
            for ($h = 0; $h < $this->hiddenSize; $h++) {
                $importance[$i] += abs($weights['ih'][$i][$h]) * abs($hidden[$h]) * abs($weights['ho'][$h][$winningClass]);
            }
        }

        $maxImp = max($importance);
        if ($maxImp > 0) {
            $importance = array_map(fn($v) => $v / $maxImp, $importance);
        }

        $grid = [];
        for ($row = 0; $row < 16; $row++) {
            $gridRow = [];
            for ($col = 0; $col < 16; $col++) {
                $gridRow[] = round($importance[$row * 16 + $col], 3);
            }
            $grid[] = $gridRow;
        }

        return $grid;
    }

    private function labelNames(Dataset $dataset): array
    {
        $labels = $dataset->label_names;

        // Older imports stored JSON twice. Accept that form so users can
        // repair their data by re-running the importer after downloading it.
        if (is_string($labels)) {
            $decoded = json_decode($labels, true);
            $labels = is_array($decoded) ? $decoded : [];
        }

        return is_array($labels) && count($labels) > 0
            ? array_values($labels)
            : ['Elliptical', 'Spiral', 'Irregular'];
    }

    public function getTestGallery(Dataset $dataset, int $count = 12): array
    {
        $labelNames = $this->labelNames($dataset);
        
        $images = GalaxyImage::where('dataset_id', $dataset->id)
            ->where('split', 'test')
            ->whereIn('label', $labelNames)
            ->inRandomOrder()
            ->limit($count)
            ->get(['id', 'image_path', 'label'])
            ->toArray();

        return array_map(function($img) {
            return [
                'id' => $img['id'],
                'image_url' => asset('storage/galaxies/' . basename($img['image_path'])),
            ];
        }, $images);
    }

    public function getMetrics(MlModel $model): array
    {
        return [
            'test_accuracy' => $model->metrics['test_accuracy'] ?? 0,
            'confusion_matrix' => $model->metrics['confusion_matrix'] ?? [],
            'per_class_accuracy' => $model->metrics['per_class_accuracy'] ?? [],
            'architecture' => $model->parameters['architecture'] ?? [],
            'label_names' => $model->parameters['label_names'] ?? [],
        ];
    }
}
