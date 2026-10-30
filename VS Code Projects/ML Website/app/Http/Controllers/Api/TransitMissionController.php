<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransitClassifyRequest;
use App\Models\Dataset;
use App\Models\KoiCandidate;
use App\Models\MlModel;
use App\Models\MissionRun;
use App\Models\Explorer;
use App\Services\ML\SvmService;
use Illuminate\Http\Request;

class TransitMissionController extends Controller
{
    public function __construct(
        private SvmService $service
    ) {}

    public function dataset(Request $request)
    {
        $dataset = $this->getDataset($request);
        
        $query = KoiCandidate::where('dataset_id', $dataset->id);

        $perPage = min($request->integer('per_page', 20), 100);
        $candidates = $query->orderBy('signal_to_noise', 'desc')->paginate($perPage);

        return response()->json([
            'dataset' => $dataset,
            'candidates' => $candidates,
            'label_mapping' => $dataset->label_names,
            'honesty_note' => 'CONFIRMED = "Exoplanet Transit Detected", FALSE POSITIVE = "Standard Star (no planet signal)". This mapping is used for educational purposes.',
        ]);
    }

    public function classify(TransitClassifyRequest $request)
    {
        $dataset = $this->getDataset($request);
        
        $model = MlModel::where('dataset_id', $dataset->id)
            ->where('name', 'like', 'svm_transit_%')
            ->first();

        if (!$model) {
            return response()->json(['error' => 'Model not trained for this dataset'], 404);
        }

        $result = $this->service->classify(
            $request->validated('transit_depth_ppm'),
            $request->validated('signal_to_noise'),
            $request->validated('lambda'),
            $model
        );

        $lightCurve = $this->service->generateLightCurve(
            $request->validated('transit_depth_ppm'),
            $request->validated('transit_duration_hours')
        );

        $boundaryData = $this->service->getDecisionBoundaryData($dataset, $model);

        $userGuess = $request->validated('user_guess_label');
        $points = 0;
        if ($userGuess && $userGuess === $result['label']) {
            $points = 100;
        }

        $explorer = $this->getExplorer($request);
        if ($explorer) {
            MissionRun::create([
                'explorer_id' => $explorer->id,
                'mission_key' => 'transit',
                'dataset_id' => $dataset->id,
                'inputs' => $request->validated(),
                'output' => $result,
                'user_guess' => ['label' => $userGuess],
                'points' => $points,
            ]);

            $explorer->increment('total_points', $points);
        }

        return response()->json([
            'classification' => $result,
            'light_curve' => $lightCurve,
            'decision_boundary' => $boundaryData,
            'points' => $points,
            'user_guess' => $userGuess,
        ]);
    }

    private function getDataset(Request $request): Dataset
    {
        $key = $request->query('dataset', 'kepler_koi');
        return Dataset::where('mission_key', 'transit')->where('key', $key)->firstOrFail();
    }

    private function getExplorer(Request $request): ?Explorer
    {
        $token = $request->header('X-Session-Token') ?? $request->input('session_token');
        if (!$token) return null;
        return Explorer::where('session_token', $token)->first();
    }
}