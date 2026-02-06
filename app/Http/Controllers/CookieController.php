<?php

namespace App\Http\Controllers;

use App\Models\CookiePreference;
use App\Models\CookieEasterEgg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Cookie Management Controller
 *
 * Handles GDPR-compliant cookie storage:
 * - Cookie preferences (1-year expiry)
 * - Easter egg discoveries (analytics)
 * - Works for non-logged users via session ID
 */
class CookieController extends Controller
{
    /**
     * Get or create session ID from cookies
     */
    private function getSessionId(Request $request): string
    {
        // Try to get from cookie first
        $sessionId = $request->cookie('session_id');

        if (!$sessionId) {
            // Try from header
            $sessionId = $request->header('X-Session-Id');
        }

        if (!$sessionId) {
            // Generate new one
            $sessionId = 'sess_' . Str::random(32);
        }

        return $sessionId;
    }

    /**
     * Set session ID cookie (1 year expiry)
     */
    private function setSessionIdCookie(string $sessionId)
    {
        return cookie('session_id', $sessionId, 525600, '/', null, true, true); // 1 year, httponly, secure
    }

    /**
     * Get cookie preferences
     */
    public function getPreferences(Request $request)
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
            'data' => [
                'analytics_consent' => $preference->analytics_consent,
                'marketing_consent' => $preference->marketing_consent,
                'preferences_consent' => $preference->preferences_consent,
                'has_consent' => true,
                'consent_date' => $preference->consent_date->toIso8601String(),
                'expires_at' => $preference->expires_at->toIso8601String()
            ]
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    /**
     * Save cookie preferences
     */
    public function savePreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'analytics_consent' => 'required|boolean',
            'marketing_consent' => 'required|boolean',
            'preferences_consent' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
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
            'data' => [
                'analytics_consent' => $preference->analytics_consent,
                'marketing_consent' => $preference->marketing_consent,
                'preferences_consent' => $preference->preferences_consent,
                'expires_at' => $preference->expires_at->toIso8601String()
            ]
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    /**
     * Get easter egg progress (analytics)
     */
    public function getEasterEggProgress(Request $request)
    {
        $sessionId = $this->getSessionId($request);

        // Check if user has analytics consent
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
            'discoveries' => $discoveries->map(function ($item) {
                return [
                    'egg_id' => $item->egg_id,
                    'discovered_at' => $item->discovered_at->toIso8601String(),
                    'metadata' => $item->metadata
                ];
            })
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    /**
     * Record easter egg discovery (analytics)
     */
    public function discoverEasterEgg(Request $request)
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

        $sessionId = $this->getSessionId($request);

        // Check analytics consent
        $preference = CookiePreference::where('session_id', $sessionId)
            ->valid()
            ->first();

        if (!$preference || !$preference->analytics_consent) {
            return response()->json([
                'success' => true,
                'message' => 'Analytics consent required for server-side tracking. Using client-side cookies only.'
            ])->withCookie($this->setSessionIdCookie($sessionId));
        }

        $eggId = $request->input('egg_id');
        $metadata = $request->input('metadata', []);

        // Check if already discovered
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

        // Create new discovery record
        $discovery = CookieEasterEgg::create([
            'session_id' => $sessionId,
            'egg_id' => $eggId,
            'discovered_at' => now(),
            'expires_at' => now()->addYear(),
            'metadata' => $metadata
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Easter egg discovered!',
            'already_discovered' => false,
            'data' => [
                'egg_id' => $discovery->egg_id,
                'discovered_at' => $discovery->discovered_at->toIso8601String()
            ]
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    /**
     * Reset easter egg progress
     */
    public function resetEasterEggProgress(Request $request)
    {
        $sessionId = $this->getSessionId($request);

        $deleted = CookieEasterEgg::where('session_id', $sessionId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Easter egg progress reset',
            'deleted_count' => $deleted
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    /**
     * Get easter egg statistics
     */
    public function getEasterEggStatistics(Request $request)
    {
        $sessionId = $this->getSessionId($request);

        $totalEggs = 18;
        $discovered = CookieEasterEgg::where('session_id', $sessionId)
            ->valid()
            ->count();

        $firstDiscovery = CookieEasterEgg::where('session_id', $sessionId)
            ->valid()
            ->orderBy('discovered_at', 'asc')
            ->first();

        $lastDiscovery = CookieEasterEgg::where('session_id', $sessionId)
            ->valid()
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
        ])->withCookie($this->setSessionIdCookie($sessionId));
    }

    /**
     * Delete expired records (cron job)
     */
    public function cleanupExpired()
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
