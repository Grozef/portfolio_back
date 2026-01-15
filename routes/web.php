<?php

/**
 * Routes web (non utilisees - API only).
 *
 * @package Routes
 */

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Portfolio API',
        'version' => '1.0.0',
        'documentation' => '/api/v1',
    ]);
});
