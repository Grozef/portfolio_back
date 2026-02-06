<?php

/**
 * API Routes - Complete Portfolio Backend
 *
 * Structure:
 * - /v1/auth/* : Authentication
 * - /v1/github/* : GitHub integration
 * - /v1/books/* : Books management
 * - /v1/contact : Contact form
 * - /v1/messages/* : Admin messages
 * - /v1/carousel/* : Carousel images
 * - /v1/cookies/* : Cookie preferences & GDPR
 * - /v1/easter-eggs/* : Easter egg tracking (analytics)
 * - /v1/health : Health check (FIXED)
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GitHubController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\CarouselImageController;
use App\Http\Controllers\CookieController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication Routes
    |--------------------------------------------------------------------------
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
    | GitHub Routes (Public)
    |--------------------------------------------------------------------------
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
    | Books Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('books')->group(function () {
        // Public
        Route::get('/', [BookController::class, 'index']);
        Route::get('/featured', [BookController::class, 'featured']);
        Route::get('/stats', [BookController::class, 'stats']);
        Route::get('/{book}', [BookController::class, 'show']);

        // Protected
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/', [BookController::class, 'store']);
            Route::put('/{book}', [BookController::class, 'update']);
            Route::delete('/{book}', [BookController::class, 'destroy']);
            Route::post('/{book}/refresh', [BookController::class, 'refreshCache']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Contact Route (Public)
    |--------------------------------------------------------------------------
    */
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:5,1');

    /*
    |--------------------------------------------------------------------------
    | Messages Routes (Protected - Admin Only)
    |--------------------------------------------------------------------------
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
    | Carousel Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('carousel')->group(function () {
        // Public
        Route::get('/', [CarouselImageController::class, 'index']);
        Route::get('/{carouselImage}', [CarouselImageController::class, 'show']);

        // Protected
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/upload', [CarouselImageController::class, 'upload'])
                ->middleware('throttle:10,1');

            Route::post('/', [CarouselImageController::class, 'store']);
            Route::put('/{carouselImage}', [CarouselImageController::class, 'update']);
            Route::delete('/{carouselImage}', [CarouselImageController::class, 'destroy']);
            Route::post('/reorder', [CarouselImageController::class, 'reorder']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Cookie Management Routes (GDPR Compliant)
    |--------------------------------------------------------------------------
    */
    Route::prefix('cookies')->group(function () {
        Route::get('/preferences', [CookieController::class, 'getPreferences']);
        Route::post('/preferences', [CookieController::class, 'savePreferences']);

        // Cleanup endpoint (for cron job)
        Route::delete('/cleanup', [CookieController::class, 'cleanupExpired']);
    });

    /*
    |--------------------------------------------------------------------------
    | Easter Eggs Routes (Analytics with Cookie Consent)
    |--------------------------------------------------------------------------
    */
    Route::prefix('easter-eggs')->group(function () {
        Route::get('/progress', [CookieController::class, 'getEasterEggProgress']);
        Route::post('/discover', [CookieController::class, 'discoverEasterEgg']);
        Route::delete('/reset', [CookieController::class, 'resetEasterEggProgress']);
        Route::get('/statistics', [CookieController::class, 'getEasterEggStatistics']);
    });

    /*
    |--------------------------------------------------------------------------
    | Health Check (FIXED - No Uri Parameter Required)
    |--------------------------------------------------------------------------
    */
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'portfolio-api',
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0.0'
        ]);
    });
});
