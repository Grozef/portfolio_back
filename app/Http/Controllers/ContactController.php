<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controleur ContactController - Gestion des messages de contact.
 *
 * CORRECTION APPLIQUEE:
 * - Rate limiting gere par le middleware throttle dans routes/api.php
 * - Validation amelioree avec limite sur message
 *
 * @package App\Http\Controllers
 */
class ContactController extends Controller
{
    /**
     * Enregistre un nouveau message de contact.
     *
     * Rate limiting: 5 messages par minute (gere par middleware throttle)
     */
    public function store(Request $request): JsonResponse
    {
        // Validation
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000', // FIXED: Added max limit
        ]);

        // Creation du message
        $message = ContactMessage::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $message,
        ], 201);
    }
}