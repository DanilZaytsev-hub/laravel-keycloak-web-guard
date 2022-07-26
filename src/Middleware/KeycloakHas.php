<?php

namespace Vizir\KeycloakWebGuard\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Vizir\KeycloakWebGuard\Exceptions\KeycloakCanException;

class KeycloakHas extends KeycloakAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard)
    {
        if (Auth::hasPermissions($guard)) {
            return $next($request);
        }

        throw new KeycloakCanException(
            'Unauthenticated.',
            [$guard],
            $this->redirectTo($request)
        );
    }
}
