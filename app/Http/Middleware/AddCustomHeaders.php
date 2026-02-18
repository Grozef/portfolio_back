<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour ajouter des headers de sécurité et des messages personnalisés.
 */
class AddCustomHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // On récupère d'abord la réponse générée par l'application
        $response = $next($request);

        // --- 1. Headers de Sécurité (Hardening) ---
        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'         => 'DENY',
            'X-XSS-Protection'        => '1; mode=block',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Permissions-Policy'      => 'geolocation=(), microphone=(), camera=()',
        ];

        foreach ($securityHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }

        // --- 2. Headers Custom (Easter Egg & Signature) ---
        $messages = [
            "Ha ! Enfin un dev back !",
            "Bienvenue dans les coulisses !",
            "Lookin' for something special ?",
            "Easter egg found: Check the X-code above !",
        ];

        $randomMessage = $messages[array_rand($messages)];

        $response->headers->set('X-Developer-Message', $randomMessage);
        $response->headers->set('X-Code', 'X_Project_Dj_Fresh_2005');

        return $response;
    }
}
