<?php

use App\Http\Controllers\GitHubController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // GitHub endpoints
    Route::prefix('github')->group(function () {
        Route::get('/profile', [GitHubController::class, 'profile']);
        Route::get('/repositories', [GitHubController::class, 'repositories']);
        Route::get('/repositories/pinned', [GitHubController::class, 'pinned']);
        Route::get('/repositories/{name}', [GitHubController::class, 'repository']);
        Route::get('/repositories/{name}/languages', [GitHubController::class, 'languages']);
    });

    // Contact endpoint
    Route::post('/contact', [ContactController::class, 'store']);

    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    });
});
