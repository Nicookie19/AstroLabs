<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExplorerOnboardRequest;
use App\Http\Requests\ExplorerUpdateRequest;
use App\Models\Explorer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExplorerController extends Controller
{
    public function onboard(ExplorerOnboardRequest $request)
    {
        $sessionToken = Str::uuid()->toString();
        
        $explorer = Explorer::create([
            'callsign' => $request->validated('callsign'),
            'level' => $request->validated('level'),
            'session_token' => $sessionToken,
            'total_points' => 0,
        ]);

        return response()->json([
            'explorer' => [
                'id' => $explorer->id,
                'callsign' => $explorer->callsign,
                'level' => $explorer->level,
                'session_token' => $explorer->session_token,
                'total_points' => $explorer->total_points,
            ],
        ], 201);
    }

    public function me(Request $request)
    {
        $explorer = $this->getExplorer($request);
        
        if (!$explorer) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        return response()->json([
            'explorer' => [
                'id' => $explorer->id,
                'callsign' => $explorer->callsign,
                'level' => $explorer->level,
                'session_token' => $explorer->session_token,
                'total_points' => $explorer->total_points,
            ],
        ]);
    }

    public function update(ExplorerUpdateRequest $request)
    {
        $explorer = $this->getExplorer($request);
        
        if (!$explorer) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $explorer->update($request->validated());

        return response()->json([
            'explorer' => [
                'id' => $explorer->id,
                'callsign' => $explorer->callsign,
                'level' => $explorer->level,
                'session_token' => $explorer->session_token,
                'total_points' => $explorer->total_points,
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