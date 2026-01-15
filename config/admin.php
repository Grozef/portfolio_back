<?php

/**
 * Configuration de l'administrateur du portfolio.
 * 
 * Ces valeurs sont utilisees par le seeder AdminSeeder
 * pour creer le compte administrateur.
 * 
 * IMPORTANT: Modifier ces valeurs dans le fichier .env
 * avant de deployer en production!
 *
 * @package Config
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Email Administrateur
    |--------------------------------------------------------------------------
    | Email utilise pour la connexion admin.
    */
    'email' => env('ADMIN_EMAIL', 'admin@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Mot de passe Administrateur
    |--------------------------------------------------------------------------
    | Mot de passe pour la connexion admin.
    | ATTENTION: Changer cette valeur en production!
    */
    'password' => env('ADMIN_PASSWORD', 'change-me-in-production'),

];
