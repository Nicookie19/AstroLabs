<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrbitPredictRequest;
use App\Models\Dataset;
use App\Models\Exoplanet;
use App\Models\MlModel;
use App\Models\MissionRun;
use App\Models\Explorer;
use App\Services\ML\LinearRegressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrbitMissionController extends Controller
{
    public function __construct(
        private LinearRegressionService $service
    ) {}

    public function dataset(Request $request)
    {
        $dataset = $this->getDataset($request);
        
        $query = Exoplanet::where('dataset_id', $dataset->id)
            ->whereNotNull('orbital_period_days')
            ->whereNotNull('semi_major_axis_au')
            ->whereNotNull('star_mass_solar');

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('host_star', 'like', "%{$request->search}%");
            });
        }

        $perPage = min($request->integer('per_page', 20), 100);
        $planets = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'dataset' => $dataset,
            'planets' => $planets,
            'honesty_note' => 'No real catalog provides exoplanet "atmospheric density" or true surface temperature. We use bulk density (g/cm³) and equilibrium temperature (K) as real substitutes.',
        ]);
    }

    public function predict(OrbitPredictRequest $request)
    {
        $dataset = $this->getDataset($request);
        
        $model = MlModel::where('dataset_id', $dataset->id)
            ->where('name', 'like', 'linear_regression_orbit_%')
            ->first();

        if (!$model) {
            return response()->json(['error' => 'Model not trained for this dataset'], 404);
        }

        $result = $this->service->predict(
            $request->validated('semi_major_axis_au'),
            $request->validated('star_mass_solar'),
            $model
        );

        $closestPlanet = $this->service->findClosestRealPlanet(
            $dataset,
            $request->validated('semi_major_axis_au'),
            $request->validated('star_mass_solar')
        );

        $comparison = $this->service->getComparisonData(
            $dataset,
            $request->validated('semi_major_axis_au'),
            $request->validated('star_mass_solar')
        );

        $userGuess = $request->validated('user_guess_days');
        $points = 0;
        if ($userGuess) {
            $errorPct = abs($userGuess - $result['period_days']) / $result['period_days'] * 100;
            if ($errorPct < 10) $points = 100;
            elseif ($errorPct < 25) $points = 50;
            elseif ($errorPct < 50) $points = 25;
        }

        $explorer = $this->getExplorer($request);
        if ($explorer) {
            MissionRun::create([
                'explorer_id' => $explorer->id,
                'mission_key' => 'orbit',
                'dataset_id' => $dataset->id,
                'inputs' => $request->validated(),
                'output' => $result,
                'user_guess' => ['period_days' => $userGuess],
                'points' => $points,
            ]);

            $explorer->increment('total_points', $points);
        }

        return response()->json([
            'prediction' => $result,
            'closest_real_planet' => $closestPlanet,
            'comparison_data' => $comparison,
            'points' => $points,
            'user_guess' => $userGuess,
        ]);
    }

    public function compareDatasets(Request $request)
    {
        $dataset = $this->getDataset($request);
        $semiMajorAxis = $request->float('semi_major_axis_au');
        $starMass = $request->float('star_mass_solar');

        if (!$semiMajorAxis || !$starMass) {
            return response()->json(['error' => 'semi_major_axis_au and star_mass_solar required'], 422);
        }

        $datasets = Dataset::where('mission_key', 'orbit')->get();
        $results = [];

        foreach ($datasets as $ds) {
            $model = MlModel::where('dataset_id', $ds->id)
                ->where('name', 'like', 'linear_regression_orbit_%')
                ->first();

            if ($model) {
                $pred = $this->service->predict($semiMajorAxis, $starMass, $model);
                $results[] = [
                    'dataset_key' => $ds->key,
                    'dataset_title' => $ds->title,
                    'prediction' => $pred,
                    'learned_exponents' => $model->metrics['kepler_exponents'] ?? null,
                    'test_r2' => $model->metrics['test_r2'] ?? null,
                ];
            }
        }

        return response()->json([
            'input' => ['semi_major_axis_au' => $semiMajorAxis, 'star_mass_solar' => $starMass],
            'comparisons' => $results,
            'explanation' => 'Different datasets yield different learned exponents because they sample different planet populations. Solar System planets follow Kepler\'s law almost perfectly (exponents ~1.5, -0.5). Exoplanet datasets show deviations due to selection effects, measurement errors, and population diversity.',
        ]);
    }

    private function getDataset(Request $request): Dataset
    {
        $key = $request->query('dataset', 'all_confirmed');
        return Dataset::where('mission_key', 'orbit')->where('key', $key)->firstOrFail();
    }

    private function getExplorer(Request $request): ?Explorer
    {
        $token = $request->header('X-Session-Token') ?? $request->input('session_token');
        if (!$token) return null;
        return Explorer::where('session_token', $token)->first();
    }
}