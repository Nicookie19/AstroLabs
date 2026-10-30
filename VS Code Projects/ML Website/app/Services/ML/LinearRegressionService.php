<?php

namespace App\Services\ML;

use App\Models\Exoplanet;
use App\Models\MlModel;
use App\Models\Dataset;
use Illuminate\Support\Facades\Log;

class LinearRegressionService
{
    public function train(Dataset $dataset): MlModel
    {
        $exoplanets = Exoplanet::where('dataset_id', $dataset->id)
            ->whereNotNull('orbital_period_days')
            ->whereNotNull('semi_major_axis_au')
            ->whereNotNull('star_mass_solar')
            ->where('orbital_period_days', '>', 0)
            ->where('semi_major_axis_au', '>', 0)
            ->where('star_mass_solar', '>', 0)
            ->get(['orbital_period_days', 'semi_major_axis_au', 'star_mass_solar'])
            ->toArray();

        if (count($exoplanets) < 10) {
            throw new \Exception('Insufficient data for training. Need at least 10 samples.');
        }

        $n = count($exoplanets);
        $split = (int)($n * 0.8);
        
        shuffle($exoplanets);
        
        $trainData = array_slice($exoplanets, 0, $split);
        $testData = array_slice($exoplanets, $split);

        $coefficients = $this->fitNormalEquations($trainData);
        
        $trainMetrics = $this->evaluate($trainData, $coefficients);
        $testMetrics = $this->evaluate($testData, $coefficients);

        $parameters = [
            'coefficients' => $coefficients,
            'feature_names' => ['intercept', 'ln_semi_major_axis', 'ln_star_mass'],
        ];

        $metrics = [
            'train_r2' => $trainMetrics['r2'],
            'test_r2' => $testMetrics['r2'],
            'train_rmse' => $trainMetrics['rmse'],
            'test_rmse' => $testMetrics['rmse'],
            'train_samples' => count($trainData),
            'test_samples' => count($testData),
            'kepler_exponents' => [
                'expected_a' => 1.5,
                'expected_m' => -0.5,
                'learned_a' => $coefficients[1],
                'learned_m' => $coefficients[2],
            ],
        ];

        return MlModel::create([
            'name' => 'linear_regression_orbit_' . $dataset->key,
            'parameters' => $parameters,
            'metrics' => $metrics,
            'dataset_id' => $dataset->id,
            'trained_at' => now(),
        ]);
    }

    public function fitNormalEquations(array $data): array
    {
        $n = count($data);

        // A fixed star mass (as in the Solar System teaching set) makes the
        // three-column normal-equation matrix singular. In that case, fit the
        // meaningful intercept + semi-major-axis relationship and retain a
        // zero mass exponent.
        $masses = array_column($data, 'star_mass_solar');
        if (max($masses) - min($masses) < 1e-10) {
            $xTx = [[0, 0], [0, 0]];
            $xTy = [0, 0];

            foreach ($data as $row) {
                $x = [1, log($row['semi_major_axis_au'])];
                $y = log($row['orbital_period_days']);
                for ($i = 0; $i < 2; $i++) {
                    $xTy[$i] += $x[$i] * $y;
                    for ($j = 0; $j < 2; $j++) {
                        $xTx[$i][$j] += $x[$i] * $x[$j];
                    }
                }
            }

            [$intercept, $axisExponent] = $this->gaussianElimination($xTx, $xTy);
            return [$intercept, $axisExponent, 0.0];
        }

        $X = [];
        $y = [];

        foreach ($data as $row) {
            $ln_a = log($row['semi_major_axis_au']);
            $ln_m = log($row['star_mass_solar']);
            $ln_t = log($row['orbital_period_days']);
            
            $X[] = [1, $ln_a, $ln_m];
            $y[] = $ln_t;
        }

        $XTX = [[0, 0, 0], [0, 0, 0], [0, 0, 0]];
        $XTy = [0, 0, 0];

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $XTy[$j] += $X[$i][$j] * $y[$i];
                for ($k = 0; $k < 3; $k++) {
                    $XTX[$j][$k] += $X[$i][$j] * $X[$i][$k];
                }
            }
        }

        return $this->gaussianElimination($XTX, $XTy);
    }

    public function gaussianElimination(array $A, array $b): array
    {
        $n = count($A);
        $augmented = [];
        
        for ($i = 0; $i < $n; $i++) {
            $augmented[$i] = array_merge($A[$i], [$b[$i]]);
        }

        for ($i = 0; $i < $n; $i++) {
            $maxRow = $i;
            for ($k = $i + 1; $k < $n; $k++) {
                if (abs($augmented[$k][$i]) > abs($augmented[$maxRow][$i])) {
                    $maxRow = $k;
                }
            }
            
            if ($maxRow !== $i) {
                [$augmented[$i], $augmented[$maxRow]] = [$augmented[$maxRow], $augmented[$i]];
            }

            if (abs($augmented[$i][$i]) < 1e-10) {
                throw new \Exception('Matrix is singular');
            }

            for ($k = $i + 1; $k < $n; $k++) {
                $factor = $augmented[$k][$i] / $augmented[$i][$i];
                for ($j = $i; $j <= $n; $j++) {
                    $augmented[$k][$j] -= $factor * $augmented[$i][$j];
                }
            }
        }

        $x = array_fill(0, $n, 0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $sum = $augmented[$i][$n];
            for ($j = $i + 1; $j < $n; $j++) {
                $sum -= $augmented[$i][$j] * $x[$j];
            }
            $x[$i] = $sum / $augmented[$i][$i];
        }

        return $x;
    }

    public function evaluate(array $data, array $coefficients): array
    {
        if (empty($data)) {
            return ['r2' => 0, 'rmse' => 0];
        }

        $predictions = [];
        $actuals = [];

        foreach ($data as $row) {
            $ln_a = log($row['semi_major_axis_au']);
            $ln_m = log($row['star_mass_solar']);
            $ln_t_actual = log($row['orbital_period_days']);
            $ln_t_pred = $coefficients[0] + $coefficients[1] * $ln_a + $coefficients[2] * $ln_m;
            
            $predictions[] = exp($ln_t_pred);
            $actuals[] = $row['orbital_period_days'];
        }

        $meanActual = array_sum($actuals) / count($actuals);
        $ssTot = 0;
        $ssRes = 0;
        $mse = 0;

        for ($i = 0; $i < count($actuals); $i++) {
            $ssTot += pow($actuals[$i] - $meanActual, 2);
            $ssRes += pow($actuals[$i] - $predictions[$i], 2);
            $mse += pow($actuals[$i] - $predictions[$i], 2);
        }

        $r2 = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0;
        $rmse = sqrt($mse / count($actuals));

        return ['r2' => $r2, 'rmse' => $rmse];
    }

    public function predict(float $semiMajorAxis, float $starMass, MlModel $model): array
    {
        $coefficients = $model->parameters['coefficients'];
        $ln_a = log($semiMajorAxis);
        $ln_m = log($starMass);
        $ln_t = $coefficients[0] + $coefficients[1] * $ln_a + $coefficients[2] * $ln_m;
        $periodDays = exp($ln_t);
        $periodYears = $periodDays / 365.25;

        $explanation = [
            'formula' => 'ln(T) = b₀ + b₁·ln(a) + b₂·ln(M)',
            'substituted' => sprintf(
                'ln(T) = %.4f + %.4f·ln(%.4f) + %.4f·ln(%.4f)',
                $coefficients[0], $coefficients[1], $semiMajorAxis, $coefficients[2], $starMass
            ),
            'steps' => [
                'ln(a) = ' . number_format($ln_a, 4),
                'ln(M) = ' . number_format($ln_m, 4),
                'ln(T) = ' . number_format($ln_t, 4),
                'T = e^' . number_format($ln_t, 4) . ' = ' . number_format($periodDays, 2) . ' days',
            ],
            'kepler_comparison' => [
                'expected_exponent_a' => 1.5,
                'learned_exponent_a' => round($coefficients[1], 4),
                'expected_exponent_m' => -0.5,
                'learned_exponent_m' => round($coefficients[2], 4),
            ],
            'model_metrics' => [
                'test_r2' => $model->metrics['test_r2'] ?? null,
                'test_rmse' => $model->metrics['test_rmse'] ?? null,
            ],
        ];

        return [
            'period_days' => round($periodDays, 2),
            'period_years' => round($periodYears, 4),
            'explanation' => $explanation,
        ];
    }

    public function getComparisonData(Dataset $dataset, float $semiMajorAxis, float $starMass, int $limit = 300): array
    {
        $exoplanets = Exoplanet::where('dataset_id', $dataset->id)
            ->whereNotNull('orbital_period_days')
            ->whereNotNull('semi_major_axis_au')
            ->whereNotNull('star_mass_solar')
            ->where('orbital_period_days', '>', 0)
            ->where('semi_major_axis_au', '>', 0)
            ->where('star_mass_solar', '>', 0)
            ->inRandomOrder()
            ->limit($limit)
            ->get(['name', 'host_star', 'orbital_period_days', 'semi_major_axis_au', 'star_mass_solar'])
            ->toArray();

        $model = MlModel::where('dataset_id', $dataset->id)
            ->where('name', 'like', 'linear_regression_orbit_%')
            ->first();

        if (!$model) {
            return ['points' => [], 'user_point' => []];
        }

        $coefficients = $model->parameters['coefficients'];
        $userPrediction = $this->predict($semiMajorAxis, $starMass, $model);

        $points = [];
        foreach ($exoplanets as $exo) {
            $ln_a = log($exo['semi_major_axis_au']);
            $ln_m = log($exo['star_mass_solar']);
            $ln_t_pred = $coefficients[0] + $coefficients[1] * $ln_a + $coefficients[2] * $ln_m;
            $periodPred = exp($ln_t_pred);
            
            $points[] = [
                'name' => $exo['name'],
                'host_star' => $exo['host_star'],
                'actual_period' => $exo['orbital_period_days'],
                'predicted_period' => round($periodPred, 2),
                'semi_major_axis' => $exo['semi_major_axis_au'],
                'star_mass' => $exo['star_mass_solar'],
            ];
        }

        return [
            'points' => $points,
            'user_point' => [
                'semi_major_axis' => $semiMajorAxis,
                'star_mass' => $starMass,
                'predicted_period' => $userPrediction['period_days'],
            ],
        ];
    }

    public function findClosestRealPlanet(Dataset $dataset, float $semiMajorAxis, float $starMass): ?array
    {
        $exoplanet = Exoplanet::where('dataset_id', $dataset->id)
            ->whereNotNull('orbital_period_days')
            ->whereNotNull('semi_major_axis_au')
            ->whereNotNull('star_mass_solar')
            ->where('orbital_period_days', '>', 0)
            ->where('semi_major_axis_au', '>', 0)
            ->where('star_mass_solar', '>', 0)
            ->get(['name', 'host_star', 'orbital_period_days', 'semi_major_axis_au', 'star_mass_solar', 'discovery_year', 'discovery_method'])
            ->map(function ($exo) use ($semiMajorAxis, $starMass) {
                $dist = sqrt(
                    pow(log($exo->semi_major_axis_au) - log($semiMajorAxis), 2) +
                    pow(log($exo->star_mass_solar) - log($starMass), 2)
                );
                $exo->distance = $dist;
                return $exo;
            })
            ->sortBy('distance')
            ->first();

        return $exoplanet ? $exoplanet->toArray() : null;
    }
}
