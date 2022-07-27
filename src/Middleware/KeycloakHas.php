<?php

namespace Vizir\KeycloakWebGuard\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class KeycloakHas 
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

        return redirect(url(config('keycloak-web.redirect_url')));
    }
}
