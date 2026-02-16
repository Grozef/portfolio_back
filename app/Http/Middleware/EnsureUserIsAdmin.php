<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Si l'utilisateur est connecté ET qu'il est admin
        if ($request->user() && $request->user()->is_admin) {
            return $next($request);
        }

        // Sinon, on claque la porte (403 Forbidden)
        abort(403, 'Accès réservé aux administrateurs.');
    }
}
