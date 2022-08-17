<?php

namespace Vizir\KeycloakWebGuard\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class KeycloakCan
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string $role
     * @param  string $resource
     * @return mixed
     */
    public function handle($request, Closure $next, $role, $resource = '')
    {
        if (empty($role) && Auth::check()) {
            return $next($request);
        }

        if (Auth::hasRole($role, $resource)) {
            return $next($request);
        }

        return redirect(url(config('keycloak-web.redirect_url')));
    }
}
