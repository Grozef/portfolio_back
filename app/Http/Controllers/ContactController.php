<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

/**
 * Controleur ContactController - Gestion des messages de contact.
 *
 * CORRECTION APPLIQUEE:
 * - Ajout du rate limiting (5 messages par heure par IP)
 *
 * @package App\Http\Controllers
 */
class ContactController extends Controller
{
    /**
     * Enregistre un nouveau message de contact.
     *
     * CORRECTION: Rate limiting ajoute - 5 messages par heure par IP.
     */
    public function store(Request $request): JsonResponse
    {
        // RATE LIMITING - Protection anti-spam
        $ip = $request->ip();
        $cacheKey = "contact_form_{$ip}";
        $attempts = Cache::get($cacheKey, 0);

        if ($attempts >= 5) {
            $remainingMinutes = Cache::store('file')->getStore()->get($cacheKey);

            return response()->json([
                'success' => false,
                'message' => 'Too many messages sent. Please try again later.',
                'retry_after' => 3600 - (time() % 3600), // Secondes restantes
            ], 429);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Creation du message
        $message = ContactMessage::create($validator->validated());

        // Incrementation du compteur de rate limiting
        if ($attempts === 0) {
            Cache::put($cacheKey, 1, now()->addHour());
        } else {
            Cache::increment($cacheKey);
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $message,
        ], 201);
    }
}