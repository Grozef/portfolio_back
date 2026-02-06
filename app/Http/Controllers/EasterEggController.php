<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EasterEggController extends Controller
{
    public function getProgress(Request $request)
    {
        // Return empty - frontend handles via cookies
        return response()->json([
            'success' => true,
            'data' => [],
            'count' => 0,
            'message' => 'Easter eggs stored in cookies'
        ]);
    }

    public function discoverEgg(Request $request)
    {
        // No server-side storage
        return response()->json([
            'success' => true,
            'message' => 'Easter egg discovery tracked via cookies'
        ]);
    }

    public function resetProgress(Request $request)
    {
        // No server-side reset
        return response()->json([
            'success' => true,
            'message' => 'Clear cookies client-side'
        ]);
    }

    public function getStatistics(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Statistics handled client-side'
        ]);
    }
}
