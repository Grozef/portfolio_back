<?php

/**
 * Configuration des services externes.
 * 
 * @package Config
 */

return [

    /*
    |--------------------------------------------------------------------------
    | GitHub API
    |--------------------------------------------------------------------------
    | Configuration pour l'integration avec l'API GitHub.
    | Le token est optionnel mais recommande pour eviter les limites de rate.
    | Sans token: 60 requetes/heure
    | Avec token: 5000 requetes/heure
    */
    'github' => [
        'username' => env('GITHUB_USERNAME', ''),
        'token' => env('GITHUB_TOKEN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Services tiers Laravel
    |--------------------------------------------------------------------------
    */
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
