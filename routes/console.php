<?php

/**
 * Routes console (commandes Artisan).
 *
 * @package Routes
 */

use Illuminate\Support\Facades\Schedule;
use App\Models\LoginAttempt;

// Nettoyage quotidien des tentatives de connexion anciennes
Schedule::call(function () {
    LoginAttempt::cleanup();
})->daily();
