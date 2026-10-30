<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExoplanetNeighborsRequest;
use App\Models\Dataset;
use App\Models\Exoplanet;
use App\Models\MlModel;
use App\Models\MissionRun;
use App\Models\Explorer;
use App\Services\ML\KnnService;
use Illuminate\Http\Request;

class ExoplanetMissionController extends Controller
{
    public function __construct(
        private KnnService $service
    ) {}

    public function dataset(Request $request)
    {
        $dataset = $this->getDataset($request);
        
        $query = Exoplanet::where('dataset_id', $dataset->id)
            ->whereNotNull('radius_earths')
            ->whereNotNull('bulk_density_g_cm3')
            ->whereNotNull('equilibrium_temp_k')
            ->whereNotNull('mass_earths');

        $perPage = min($request->integer('per_page', 20), 100);
        $planets = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'dataset' => $dataset,
            'planets' => $planets,
            'presets' => $this->service->getPresets(),
            'honesty_note' => 'Planet type is derived from MASS only (not used as features): <10 Earth masses = Terrestrial/Super-Earth, 10-50 = Neptune-like, >50 = Gas giant. Features: equilibrium temperature, radius, bulk density. This makes the classification non-trivial.',
        ]);
    }

    public function neighbors(ExoplanetNeighborsRequest $request)
    {
        $dataset = $this->getDataset($request);
        
        $model = MlModel::where('dataset_id', $dataset->id)
            ->where('name', 'like', 'knn_exoplanet_%')
            ->first();

        if (!$model) {
            return response()->json(['error' => 'Model not trained for this dataset'], 404);
        }

        $features = [
            'ln_radius' => log($request->validated('radius_earths')),
            'ln_density' => log($request->validated('bulk_density_g_cm3')),
            'eq_temp' => $request->validated('equilibrium_temp_k'),
        ];

        $result = $this->service->findNeighbors($features, $model, $request->validated('k'));

        $userGuess = $request->validated('user_guess_type');
        $points = 0;
        if ($userGuess && $userGuess === $result['prediction']) {
            $points = 100;
        }

        $explorer = $this->getExplorer($request);
        if ($explorer) {
            MissionRun::create([
                'explorer_id' => $explorer->id,
                'mission_key' => 'exoplanets',
                'dataset_id' => $dataset->id,
                'inputs' => $request->validated(),
                'output' => $result,
                'user_guess' => ['type' => $userGuess],
                'points' => $points,
            ]);

            $explorer->increment('total_points', $points);
        }

        return response()->json([
            'prediction' => $result,
            'points' => $points,
            'user_guess' => $userGuess,
        ]);
    }

    private function getDataset(Request $request): Dataset
    {
        $key = $request->query('dataset', 'confirmed_exoplanets');
        return Dataset::where('mission_key', 'exoplanets')->where('key', $key)->firstOrFail();
    }

    private function getExplorer(Request $request): ?Explorer
    {
        $token = $request->header('X-Session-Token') ?? $request->input('session_token');
        if (!$token) return null;
        return Explorer::where('session_token', $token)->first();
    }
}