<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GalaxyClassifyRequest;
use App\Http\Requests\GalaxyRevealRequest;
use App\Models\Dataset;
use App\Models\GalaxyImage;
use App\Models\MlModel;
use App\Models\MissionRun;
use App\Models\Explorer;
use App\Services\ML\NeuralNetworkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GalaxyMissionController extends Controller
{
    public function __construct(
        private NeuralNetworkService $service
    ) {}

    public function gallery(Request $request)
    {
        $dataset = $this->getDataset($request);
        
        $images = $this->service->getTestGallery($dataset, $request->integer('count', 12));

        return response()->json([
            'dataset' => $dataset,
            'images' => $images,
            'honesty_note' => 'Network sees 16×16 grayscale (256 inputs) → 24 tanh hidden → 3 softmax outputs. Tiny network on tiny images makes mistakes; see confusion matrix.',
        ]);
    }

    public function classify(GalaxyClassifyRequest $request)
    {
        $dataset = $this->getDataset($request);
        
        $model = MlModel::where('dataset_id', $dataset->id)
            ->where('name', 'like', 'neural_network_galaxy_%')
            ->latest('id')
            ->first();

        if (!$model) {
            return response()->json(['error' => 'Model not trained for this dataset'], 404);
        }

        $pixels = $request->validated('pixels');
        
        if (!$pixels && $request->filled('image_id')) {
            $image = GalaxyImage::where('dataset_id', $dataset->id)
                ->where('split', 'test')
                ->find($request->validated('image_id'));
            
            if (!$image) {
                return response()->json(['error' => 'Image not found'], 404);
            }
            
            $pixels = $image->pixels;
        }

        if (!$pixels || count($pixels) !== 256) {
            return response()->json(['error' => 'Invalid pixel data: must provide 256 values (16x16)'], 422);
        }

        $result = $this->service->predict($pixels, $model);

        $userGuess = $request->validated('user_guess_label');
        $points = 0;

        $explorer = $this->getExplorer($request);
        $run = null;
        if ($explorer) {
            $run = MissionRun::create([
                'explorer_id' => $explorer->id,
                'mission_key' => 'galaxy',
                'dataset_id' => $dataset->id,
                'inputs' => ['pixels' => $pixels, 'image_id' => $request->input('image_id')],
                'output' => $result,
                'user_guess' => ['label' => $userGuess],
                'points' => 0, // Points awarded on reveal
            ]);
        }

        return response()->json([
            'classification' => $result,
            'user_guess' => $userGuess,
            'points' => $points,
            'awaiting_reveal' => true,
            'run_id' => $run?->id,
        ]);
    }

    public function reveal(GalaxyRevealRequest $request)
    {
        $dataset = $this->getDataset($request);
        
        $image = GalaxyImage::where('dataset_id', $dataset->id)
            ->where('split', 'test')
            ->findOrFail($request->validated('image_id'));

        $explorer = $this->getExplorer($request);
        abort_unless($explorer, 401, 'Complete onboarding before revealing a galaxy.');

        return DB::transaction(function () use ($request, $dataset, $image, $explorer) {
            $run = MissionRun::where('explorer_id', $explorer->id)
                ->where('mission_key', 'galaxy')
                ->where('dataset_id', $dataset->id)
                ->where('inputs->image_id', $image->id)
                ->when($request->filled('run_id'), fn ($query) => $query->whereKey($request->validated('run_id')))
                ->latest('id')
                ->lockForUpdate()
                ->first();

            abort_unless($run, 409, 'Run the neural network on this galaxy before revealing its label.');

            // Score the saved round, never editable guesses sent by the browser.
            $userGuess = $run->user_guess['label'];
            $modelGuess = $run->output['prediction'];
            $trueLabel = $image->label;
            $userCorrect = $userGuess === $trueLabel;
            $modelCorrect = $modelGuess === $trueLabel;
            $points = $userCorrect ? ($modelCorrect ? 100 : 150) : ($modelCorrect ? 25 : 10);

            // Every reveal awards at least 10 points; a scored round is final.
            if ($run->points === 0) {
                $run->update(['points' => $points]);
                $explorer->increment('total_points', $points);
            }

            return response()->json([
                'true_label' => $trueLabel,
                'user_guess' => $userGuess,
                'model_guess' => $modelGuess,
                'user_correct' => $userCorrect,
                'model_correct' => $modelCorrect,
                'points' => $run->points,
                'run_id' => $run->id,
                'image_url' => asset('storage/galaxies/' . basename($image->image_path)),
            ]);
        });
    }

    public function metrics(Request $request)
    {
        $dataset = $this->getDataset($request);
        
        $model = MlModel::where('dataset_id', $dataset->id)
            ->where('name', 'like', 'neural_network_galaxy_%')
            ->latest('id')
            ->first();

        if (!$model) {
            return response()->json(['error' => 'Model not trained for this dataset'], 404);
        }

        return response()->json([
            'metrics' => $this->service->getMetrics($model),
        ]);
    }

    private function getDataset(Request $request): Dataset
    {
        $key = $request->input('dataset');
        $query = Dataset::where('mission_key', 'galaxy');

        if ($key) {
            return $query->where('key', $key)->firstOrFail();
        }

        return $query->withCount('galaxyImages')
            ->orderByRaw('CASE WHEN galaxy_images_count > 0 THEN 0 ELSE 1 END')
            ->orderByDesc('is_default')->orderBy('id')->firstOrFail();
    }

    private function getExplorer(Request $request): ?Explorer
    {
        $token = $request->header('X-Session-Token') ?? $request->input('session_token');
        if (!$token) return null;
        return Explorer::where('session_token', $token)->first();
    }
}
