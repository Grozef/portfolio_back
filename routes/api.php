<?php

/**
 * Routes API du portfolio.
 *
 * Structure:
 * - /v1/auth/* : Authentification (login, logout, me)
 * - /v1/github/* : Integration GitHub (public)
 * - /v1/books/* : Gestion des livres (GET public, POST/PUT/DELETE protege)
 * - /v1/contact : Formulaire de contact (public)
 * - /v1/messages/* : Gestion admin des messages (protege)
 * - /v1/health : Health check (public)
 *
 * @package Routes
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GitHubController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Routes d'authentification
    |--------------------------------------------------------------------------
    | POST /auth/login  - Connexion (avec protection brute force)
    | POST /auth/logout - Deconnexion (protege)
    | GET  /auth/me     - Utilisateur connecte (protege)
    */
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Routes GitHub (publiques)
    |--------------------------------------------------------------------------
    | GET /github/profile              - Profil utilisateur
    | GET /github/repositories         - Liste des repos
    | GET /github/repositories/pinned  - Repos epingles
    | GET /github/repositories/{name}  - Detail d'un repo
    | GET /github/repositories/{name}/languages - Langages d'un repo
    */
    Route::prefix('github')->group(function () {
        Route::get('/profile', [GitHubController::class, 'profile']);
        Route::get('/repositories', [GitHubController::class, 'repositories']);
        Route::get('/repositories/pinned', [GitHubController::class, 'pinned']);
        Route::get('/repositories/{name}', [GitHubController::class, 'repository']);
        Route::get('/repositories/{name}/languages', [GitHubController::class, 'languages']);
    });

    /*
    |--------------------------------------------------------------------------
    | Routes Books
    |--------------------------------------------------------------------------
    | Routes publiques:
    | GET /books           - Liste des livres
    | GET /books/featured  - Livres mis en avant
    | GET /books/stats     - Statistiques
    | GET /books/{id}      - Detail d'un livre
    |
    | Routes protegees (auth requise):
    | POST   /books              - Ajouter un livre
    | PUT    /books/{id}         - Modifier un livre
    | DELETE /books/{id}         - Supprimer un livre
    | POST   /books/{id}/refresh - Rafraichir le cache
    */
    Route::prefix('books')->group(function () {
        // Public: lecture seule
        Route::get('/', [BookController::class, 'index']);
        Route::get('/featured', [BookController::class, 'featured']);
        Route::get('/stats', [BookController::class, 'stats']);
        Route::get('/{book}', [BookController::class, 'show']);

        // Protege: CRUD admin
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/', [BookController::class, 'store']);
            Route::put('/{book}', [BookController::class, 'update']);
            Route::delete('/{book}', [BookController::class, 'destroy']);
            Route::post('/{book}/refresh', [BookController::class, 'refreshCache']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Route Contact (publique)
    |--------------------------------------------------------------------------
    | POST /contact - Envoi d'un message de contact
    */
    Route::post('/contact', [ContactController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Routes Messages (protegees - admin only)
    |--------------------------------------------------------------------------
    | GET    /messages           - Liste des messages
    | GET    /messages/{id}      - Detail d'un message
    | PATCH  /messages/{id}/read   - Marquer comme lu
    | PATCH  /messages/{id}/unread - Marquer comme non lu
    | DELETE /messages/{id}      - Supprimer un message
    */
    Route::prefix('messages')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [MessageController::class, 'index']);
        Route::get('/{message}', [MessageController::class, 'show']);
        Route::patch('/{message}/read', [MessageController::class, 'markAsRead']);
        Route::patch('/{message}/unread', [MessageController::class, 'markAsUnread']);
        Route::delete('/{message}', [MessageController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Health Check (publique)
    |--------------------------------------------------------------------------
    | GET /health - Verification de l'etat de l'API
    */
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    });
});