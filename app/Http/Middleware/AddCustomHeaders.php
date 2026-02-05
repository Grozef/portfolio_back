<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to add custom HTTP headers to all responses.
 *
 * Includes easter egg header for developers who inspect network traffic.
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
        $response = $next($request);

        // Add custom developer message header (Easter Egg #10)
        $messages = [
            "Ha ! Enfin un dev back !",
            "Bienvenue dans les coulisses !",
            "Tu cherches quelque chose de special ?",
            "Easter egg found: Check the headers!",
        ];

        $randomMessage = $messages[array_rand($messages)];
        $response->headers->set('X-Developer-Message', $randomMessage);

        // Add easter egg hint
        $response->headers->set('X-Easter-Egg-Hint', 'There are ' . (14 - 1) . ' more to find!');

        // Add portfolio signature
        $response->headers->set('X-Powered-By', 'Coffee and Determination');

        return $response;
    }
}
