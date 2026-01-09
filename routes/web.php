<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Portfolio API',
        'version' => '1.0.0',
    ]);
});
