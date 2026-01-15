<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Controleur AuthController - Gestion de l'authentification.
 * 
 * Gere la connexion, deconnexion et verification de l'utilisateur admin.
 * Inclut une protection contre les attaques par force brute:
 * - Maximum 3 tentatives echouees
 * - Blocage de 15 minutes apres 3 echecs
 * - Suivi par email ET par adresse IP
 *
 * @package App\Http\Controllers
 */
class AuthController extends Controller
{
    /**
     * Authentifie un utilisateur et retourne un token.
     * 
     * Protection brute force:
     * - Verifie si l'email/IP est bloque avant toute tentative
     * - Enregistre chaque tentative (reussie ou non)
     * - Bloque apres 3 tentatives echouees pendant 15 minutes
     *
     * @param Request $request
     * @return JsonResponse Token d'authentification ou erreur
     * 
     * @throws ValidationException Si les identifiants sont invalides
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "user": {"id": 1, "name": "Admin", "email": "admin@example.com"},
     *     "token": "1|abc123..."
     *   }
     * }
     * @response 429 {
     *   "success": false,
     *   "message": "Too many login attempts. Please try again in X seconds.",
     *   "retry_after": 900
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->input('email');
        $ipAddress = $request->ip();

        // Verification du blocage (protection brute force)
        if (LoginAttempt::isBlocked($email, $ipAddress)) {
            $remainingSeconds = LoginAttempt::remainingLockoutSeconds($email, $ipAddress);
            
            return response()->json([
                'success' => false,
                'message' => "Too many login attempts. Please try again in {$remainingSeconds} seconds.",
                'retry_after' => $remainingSeconds,
            ], 429);
        }

        // Recherche de l'utilisateur
        $user = User::where('email', $email)->first();

        // Verification des identifiants
        if (!$user || !Hash::check($request->password, $user->password)) {
            // Enregistrement de la tentative echouee
            LoginAttempt::record($email, $ipAddress, false);

            $remainingAttempts = LoginAttempt::MAX_ATTEMPTS - LoginAttempt::recentFailedAttempts($email, $ipAddress);

            throw ValidationException::withMessages([
                'email' => [
                    $remainingAttempts > 0
                        ? "Invalid credentials. {$remainingAttempts} attempt(s) remaining."
                        : 'Account locked. Please try again later.'
                ],
            ]);
        }

        // Connexion reussie - enregistrement et nettoyage
        LoginAttempt::record($email, $ipAddress, true);

        // Revocation des anciens tokens
        $user->tokens()->delete();

        // Creation du nouveau token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Deconnecte l'utilisateur en revoquant son token actuel.
     *
     * @param Request $request
     * @return JsonResponse Confirmation de deconnexion
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Logged out successfully"
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Retourne les informations de l'utilisateur connecte.
     *
     * @param Request $request
     * @return JsonResponse Donnees de l'utilisateur
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {"id": 1, "name": "Admin", "email": "admin@example.com"}
     * }
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    }
}
