<?php

namespace App\Http\Controllers;

use App\Models\CookiePreference;
use App\Models\CookieEasterEgg;
use App\Http\Resources\CookiePreferenceResource;
use App\Http\Resources\CookieEasterEggResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CookieController extends Controller
{
    private function getSessionId(Request $request): string
    {
        $sessionId = $request->cookie('session_id') ?? $request->header('X-Session-Id');
        return $sessionId ?? 'sess_' . Str::random(32);
    }

    private function setSessionIdCookie(string $sessionId)
    {
        return cookie('session_id', $sessionId, 525600, '/', null, true, true);
    }

    public function getPreferences(Request $request): JsonResponse
    {
        $sessionId = $this->getSessionId($request);

        $preference = CookiePreference::where('session_id', $sessionId)
            ->valid()
            ->first();

        if (!$preference) {
            return response()->json([
                'success' => true,
                'data' => [
                    'analytics_consent' => false,
                    'marketing_consent' => false,
                    'preferences_consent' => false,
                    'has_consent' => false
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new CookiePreferenceResource($preference)
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    public function savePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'analytics_consent' => 'required|boolean',
            'marketing_consent' => 'required|boolean',
            'preferences_consent' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $sessionId = $this->getSessionId($request);

        $preference = CookiePreference::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'analytics_consent' => $request->analytics_consent,
                'marketing_consent' => $request->marketing_consent,
                'preferences_consent' => $request->preferences_consent,
                'consent_date' => now(),
                'expires_at' => now()->addYear()
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Cookie preferences saved',
            'data' => new CookiePreferenceResource($preference)
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    public function getEasterEggProgress(Request $request): JsonResponse
    {
        $sessionId = $this->getSessionId($request);

        $preference = CookiePreference::where('session_id', $sessionId)
            ->valid()
            ->first();

        if (!$preference || !$preference->analytics_consent) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Analytics consent required for server-side tracking'
            ])->withCookie($this->setSessionIdCookie($sessionId));
        }

        $discoveries = CookieEasterEgg::where('session_id', $sessionId)
            ->valid()
            ->orderBy('discovered_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $discoveries->pluck('egg_id')->toArray(),
            'count' => $discoveries->count(),
            'discoveries' => CookieEasterEggResource::collection($discoveries)
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    public function discoverEasterEgg(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'egg_id' => 'required|string|max:50',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $sessionId = $this->getSessionId($request);
        $preference = CookiePreference::where('session_id', $sessionId)->valid()->first();

        if (!$preference || !$preference->analytics_consent) {
            return response()->json([
                'success' => true,
                'message' => 'Analytics consent required for server-side tracking. Using client-side cookies only.'
            ])->withCookie($this->setSessionIdCookie($sessionId));
        }

        $eggId = $request->input('egg_id');
        $existing = CookieEasterEgg::where('session_id', $sessionId)
            ->where('egg_id', $eggId)
            ->valid()
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Easter egg already discovered',
                'already_discovered' => true
            ])->withCookie($this->setSessionIdCookie($sessionId));
        }

        $discovery = CookieEasterEgg::create([
            'session_id' => $sessionId,
            'egg_id' => $eggId,
            'discovered_at' => now(),
            'expires_at' => now()->addYear(),
            'metadata' => $request->input('metadata', [])
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Easter egg discovered!',
            'already_discovered' => false,
            'data' => new CookieEasterEggResource($discovery)
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    public function resetEasterEggProgress(Request $request): JsonResponse
    {
        $sessionId = $this->getSessionId($request);
        $deleted = CookieEasterEgg::where('session_id', $sessionId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Easter egg progress reset',
            'deleted_count' => $deleted
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    public function getEasterEggStatistics(Request $request): JsonResponse
    {
        $sessionId = $this->getSessionId($request);
        $totalEggs = 18;

        $discoveries = CookieEasterEgg::where('session_id', $sessionId)
            ->valid()
            ->orderBy('discovered_at', 'asc')
            ->get();

        $count = $discoveries->count();
        $first = $discoveries->first();
        $last = $discoveries->last();

        return response()->json([
            'success' => true,
            'data' => [
                'total_eggs' => $totalEggs,
                'discovered' => $count,
                'remaining' => $totalEggs - $count,
                'percentage' => round(($count / $totalEggs) * 100, 2),
                'first_discovery' => $first ? $first->discovered_at->toIso8601String() : null,
                'last_discovery' => $last ? $last->discovered_at->toIso8601String() : null
            ]
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    public function cleanupExpired(): JsonResponse
    {
        $expiredPreferences = CookiePreference::where('expires_at', '<', now())->delete();
        $expiredEggs = CookieEasterEgg::where('expires_at', '<', now())->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expired records cleaned up',
            'deleted' => [
                'preferences' => $expiredPreferences,
                'easter_eggs' => $expiredEggs
            ]
        ]);
    }
}
