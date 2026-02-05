<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Middleware Authenticate - Handle unauthenticated users.
 *
 * For API requests, returns 401 Unauthorized.
 *
 * @package App\Http\Middleware
 */
class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when not authenticated.
     *
     * @param Request $request
     * @return string|null
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API requests, don't redirect - return 401
        return $request->expectsJson() ? null : null;
    }
}
