<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MissionRun;
use App\Models\MissionProgress;
use App\Models\Explorer;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function index(Request $request)
    {
        $explorer = $this->getExplorer($request);
        
        if (!$explorer) {
            return response()->json([
                'missions_tried' => 0,
                'total_points' => 0,
                'completed_missions' => [],
                'runs' => [],
            ]);
        }

        $runs = MissionRun::where('explorer_id', $explorer->id)
            ->with('dataset')
            ->orderBy('created_at', 'desc')
            ->get();

        $completedMissions = $runs->groupBy('mission_key')->keys()->toArray();

        return response()->json([
            'explorer' => [
                'callsign' => $explorer->callsign,
                'level' => $explorer->level,
                'total_points' => $explorer->total_points,
            ],
            'missions_tried' => count($completedMissions),
            'total_points' => $explorer->total_points,
            'completed_missions' => $completedMissions,
            'runs' => $runs->map(function($run) {
                return [
                    'id' => $run->id,
                    'mission_key' => $run->mission_key,
                    'dataset_key' => $run->dataset->key ?? null,
                    'dataset_title' => $run->dataset->title ?? null,
                    'inputs' => $run->inputs,
                    'output' => $run->output,
                    'user_guess' => $run->user_guess,
                    'points' => $run->points,
                    'created_at' => $run->created_at,
                ];
            }),
        ]);
    }

    public function reload(Request $request, int $runId)
    {
        $explorer = $this->getExplorer($request);
        
        if (!$explorer) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $run = MissionRun::where('explorer_id', $explorer->id)
            ->where('id', $runId)
            ->firstOrFail();

        return response()->json([
            'run' => [
                'id' => $run->id,
                'mission_key' => $run->mission_key,
                'dataset_id' => $run->dataset_id,
                'inputs' => $run->inputs,
                'output' => $run->output,
                'user_guess' => $run->user_guess,
                'points' => $run->points,
                'created_at' => $run->created_at,
            ],
        ]);
    }

    private function getExplorer(Request $request): ?Explorer
    {
        $token = $request->header('X-Session-Token') ?? $request->input('session_token');
        if (!$token) return null;
        return Explorer::where('session_token', $token)->first();
    }
}