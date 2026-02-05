<?php

namespace App\Http\Controllers;

use App\Models\EasterEggProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for easter egg discovery tracking.
 * 
 * Handles storage and retrieval of easter egg progress.
 */
class EasterEggController extends Controller
{
    /**
     * Get all discovered easter eggs for current session.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProgress(Request $request)
    {
        $sessionId = $request->header('X-Session-Id') ?? session()->getId();
        
        $discoveries = EasterEggProgress::where('session_id', $sessionId)
            ->orderBy('discovered_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $discoveries->pluck('egg_id')->toArray(),
            'count' => $discoveries->count(),
            'discoveries' => $discoveries->map(function ($item) {
                return [
                    'egg_id' => $item->egg_id,
                    'discovered_at' => $item->discovered_at->toIso8601String(),
                    'metadata' => $item->metadata
                ];
            })
        ]);
    }

    /**
     * Record a new easter egg discovery.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function discoverEgg(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'egg_id' => 'required|string|max:50',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $sessionId = $request->header('X-Session-Id') ?? session()->getId();
        $eggId = $request->input('egg_id');
        $metadata = $request->input('metadata', []);

        // Check if already discovered
        $existing = EasterEggProgress::where('session_id', $sessionId)
            ->where('egg_id', $eggId)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Easter egg already discovered',
                'already_discovered' => true
            ]);
        }

        // Create new discovery record
        $progress = EasterEggProgress::create([
            'session_id' => $sessionId,
            'egg_id' => $eggId,
            'discovered_at' => now(),
            'metadata' => $metadata
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Easter egg discovered!',
            'already_discovered' => false,
            'data' => [
                'egg_id' => $progress->egg_id,
                'discovered_at' => $progress->discovered_at->toIso8601String()
            ]
        ]);
    }

    /**
     * Reset all easter egg progress for current session.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetProgress(Request $request)
    {
        $sessionId = $request->header('X-Session-Id') ?? session()->getId();
        
        $deleted = EasterEggProgress::where('session_id', $sessionId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Easter egg progress reset',
            'deleted_count' => $deleted
        ]);
    }

    /**
     * Get statistics about easter egg discoveries.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics(Request $request)
    {
        $sessionId = $request->header('X-Session-Id') ?? session()->getId();
        
        $totalEggs = 14; // Update this when adding new easter eggs
        $discovered = EasterEggProgress::where('session_id', $sessionId)->count();
        
        $firstDiscovery = EasterEggProgress::where('session_id', $sessionId)
            ->orderBy('discovered_at', 'asc')
            ->first();
            
        $lastDiscovery = EasterEggProgress::where('session_id', $sessionId)
            ->orderBy('discovered_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total_eggs' => $totalEggs,
                'discovered' => $discovered,
                'remaining' => $totalEggs - $discovered,
                'percentage' => round(($discovered / $totalEggs) * 100, 2),
                'first_discovery' => $firstDiscovery ? $firstDiscovery->discovered_at->toIso8601String() : null,
                'last_discovery' => $lastDiscovery ? $lastDiscovery->discovered_at->toIso8601String() : null
            ]
        ]);
    }
}
