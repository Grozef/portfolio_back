<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use Illuminate\Http\JsonResponse;

class SecurityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'metrics' => LoginAttempt::getSecurityMetrics(),
            'alerts'  => LoginAttempt::getBruteForceAlerts(),
            'stats_24h' => LoginAttempt::getStats24h(),
        ]);
    }
}
