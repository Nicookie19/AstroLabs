<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TelescopeRecommendRequest;
use App\Models\Dataset;
use App\Models\MissionRun;
use App\Models\TelescopeTarget;
use App\Models\Explorer;
use App\Services\ML\DecisionTreeService;
use Illuminate\Http\Request;

class TelescopeMissionController extends Controller
{
    public function __construct(
        private DecisionTreeService $service
    ) {}

    public function targets(Request $request)
    {
        $dataset = $this->getDataset($request);
        
        $targets = $this->service->getTargets($dataset, $request->query('search'));

        return response()->json([
            'dataset' => $dataset,
            'targets' => $targets,
            'honesty_note' => 'No public dataset of "correct telescope settings" exists. The decision tree rules are standard astrophotography rules of thumb written by the team, NOT learned from data.',
        ]);
    }

    public function recommend(TelescopeRecommendRequest $request)
    {
        $dataset = $this->getDataset($request);
        
        $target = TelescopeTarget::where('dataset_id', $dataset->id)
            ->findOrFail($request->validated('target_id'));

        $result = $this->service->recommend(
            $request->validated('bortle_scale'),
            $target,
            $request->validated('user_guess')
        );

        $userGuess = $request->validated('user_guess');
        $points = $result['explanation']['score'] ?? 0;

        $explorer = $this->getExplorer($request);
        if ($explorer) {
            MissionRun::create([
                'explorer_id' => $explorer->id,
                'mission_key' => 'telescope',
                'dataset_id' => $dataset->id,
                'inputs' => $request->validated(),
                'output' => $result,
                'user_guess' => $userGuess,
                'points' => $points,
            ]);

            $explorer->increment('total_points', $points);
        }

        return response()->json([
            'recommendation' => $result,
            'points' => $points,
            'user_guess' => $userGuess,
        ]);
    }

    private function getDataset(Request $request): Dataset
    {
        $key = $request->query('dataset', 'messier');
        return Dataset::where('mission_key', 'telescope')->where('key', $key)->firstOrFail();
    }

    private function getExplorer(Request $request): ?Explorer
    {
        $token = $request->header('X-Session-Token') ?? $request->input('session_token');
        if (!$token) return null;
        return Explorer::where('session_token', $token)->first();
    }
}