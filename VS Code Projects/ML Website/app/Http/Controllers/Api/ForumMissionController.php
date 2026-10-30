<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForumClassifyRequest;
use App\Models\Dataset;
use App\Models\ForumQuestion;
use App\Models\MlModel;
use App\Models\MissionRun;
use App\Models\Explorer;
use App\Services\ML\NaiveBayesService;
use Illuminate\Http\Request;

class ForumMissionController extends Controller
{
    public function __construct(
        private NaiveBayesService $service
    ) {}

    public function dataset(Request $request)
    {
        $dataset = $this->getDataset($request);
        
        $query = ForumQuestion::where('dataset_id', $dataset->id);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $perPage = min($request->integer('per_page', 20), 100);
        $questions = $query->orderBy('score', 'desc')->paginate($perPage);

        $stats = $this->service->getDatasetStats($dataset);

        return response()->json([
            'dataset' => $dataset,
            'questions' => $questions,
            'category_counts' => $stats,
            'attribution' => 'Data from Astronomy Stack Exchange and Space Exploration Stack Exchange via Stack Exchange API. Content licensed under CC BY-SA 4.0.',
        ]);
    }

    public function classify(ForumClassifyRequest $request)
    {
        $dataset = $this->getDataset($request);
        
        $model = MlModel::where('dataset_id', $dataset->id)
            ->where('name', 'like', 'naive_bayes_forum_%')
            ->first();

        if (!$model) {
            return response()->json(['error' => 'Model not trained for this dataset'], 404);
        }

        $result = $this->service->classify($request->validated('question'), $model);

        $userGuess = $request->validated('user_guess_category');
        $points = 0;
        if ($userGuess && $userGuess === $result['category']) {
            $points = 100;
        }

        $explorer = $this->getExplorer($request);
        if ($explorer) {
            MissionRun::create([
                'explorer_id' => $explorer->id,
                'mission_key' => 'forum',
                'dataset_id' => $dataset->id,
                'inputs' => $request->validated(),
                'output' => $result,
                'user_guess' => ['category' => $userGuess],
                'points' => $points,
            ]);

            $explorer->increment('total_points', $points);
        }

        return response()->json([
            'classification' => $result,
            'points' => $points,
            'user_guess' => $userGuess,
        ]);
    }

    private function getDataset(Request $request): Dataset
    {
        $key = $request->query('dataset', 'stackexchange_qa');
        return Dataset::where('mission_key', 'forum')->where('key', $key)->firstOrFail();
    }

    private function getExplorer(Request $request): ?Explorer
    {
        $token = $request->header('X-Session-Token') ?? $request->input('session_token');
        if (!$token) return null;
        return Explorer::where('session_token', $token)->first();
    }
}