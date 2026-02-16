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
    $count = LoginAttempt::cleanup();
    logger()->info("Nettoyage des tentatives de connexion : $count entrées supprimées.");
})->daily();
