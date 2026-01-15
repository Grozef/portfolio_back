<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controleur ContactController - Gestion des messages de contact.
 * 
 * Permet aux visiteurs d'envoyer des messages via le formulaire de contact.
 *
 * @package App\Http\Controllers
 */
class ContactController extends Controller
{
    /**
     * Enregistre un nouveau message de contact.
     *
     * @param Request $request
     * @return JsonResponse Message cree ou erreurs de validation
     * 
     * @bodyParam name string required Nom de l'expediteur (max 255)
     * @bodyParam email string required Email valide (max 255)
     * @bodyParam subject string Sujet du message (max 255)
     * @bodyParam message string required Contenu du message (max 5000)
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "Message sent successfully",
     *   "data": {"id": 1, "name": "John", ...}
     * }
     * @response 422 {
     *   "success": false,
     *   "errors": {"email": ["The email field must be a valid email address."]}
     * }
     */
    public function store(Request $request): JsonResponse
    {
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

        $message = ContactMessage::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $message,
        ], 201);
    }
}
