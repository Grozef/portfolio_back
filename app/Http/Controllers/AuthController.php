<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $email = $credentials['email'];
        $ipAddress = $request->ip();

        if (LoginAttempt::isBlocked($email, $ipAddress)) {
            $remainingSeconds = LoginAttempt::remainingLockoutSeconds($email, $ipAddress);

            return response()->json([
                'success' => false,
                'message' => "Trop de tentatives. Réessayez dans {$remainingSeconds} secondes.",
                'retry_after' => $remainingSeconds,
            ], 429);
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            LoginAttempt::record($email, $ipAddress, false);
            $remainingAttempts = LoginAttempt::MAX_ATTEMPTS - LoginAttempt::recentFailedAttempts($email, $ipAddress);

            throw ValidationException::withMessages([
                'email' => [
                    $remainingAttempts > 0
                        ? "Identifiants invalides. Il vous reste {$remainingAttempts} tentative(s)."
                        : 'Compte verrouillé. Veuillez patienter.'
                ],
            ]);
        }

        LoginAttempt::record($email, $ipAddress, true);
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user()),
        ]);
    }
}
