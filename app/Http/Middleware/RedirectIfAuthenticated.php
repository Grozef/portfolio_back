<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware RedirectIfAuthenticated - Handle already authenticated users.
 *
 * Prevents authenticated users from accessing guest-only routes.
 *
 * @package App\Http\Middleware
 */
class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null ...$guards
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // For API, return 403 Forbidden
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Already authenticated',
                    ], 403);
                }

                // For web, redirect to home
                return redirect('/');
            }
        }

        return $next($request);
    }
}
