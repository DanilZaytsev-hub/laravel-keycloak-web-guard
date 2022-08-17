<?php

namespace Vizir\KeycloakWebGuard\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Config;

/**
 * @method static getLoginUrl()
 * @method static getLogoutUrl()
 * @method static getAccessToken(string $code)
 * @method static getUserProfile(array $credentials)
 * @method static forgetToken()
 */
class KeycloakWeb extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'keycloak-web';
    }


    public static function routes()
    {
        static::registerRoutes();
    }

    /**
     * Register the authentication routes for keycloak.
     *
     * @return void
     */
    private static function registerRoutes()
    {
        $defaults = [
            'login' => 'login',
            'logout' => 'logout',
            'register' => 'register',
            'callback' => 'callback',
        ];

        $routes = Config::get('keycloak-web.routes', []);
        $routes = array_merge($defaults, $routes);

        // Register Routes
        $router =  static::$app->make('router');

        if (!empty($routes['login'])) {
            $router->middleware('web')->get(
                $routes['login'],
                '\Vizir\KeycloakWebGuard\Controllers\AuthController@login'
            )->name('login');
        }

        if (!empty($routes['logout'])) {
            $router->middleware('web')->get(
                $routes['logout'],
                '\Vizir\KeycloakWebGuard\Controllers\AuthController@logout'
            )->name('logout');
        }

        if (!empty($routes['register'])) {
            $router->middleware('web')->get(
                $routes['register'],
                '\Vizir\KeycloakWebGuard\Controllers\AuthController@register'
            )->name('register');
        }

        if (!empty($routes['callback'])) {
            $router->middleware('web')->get(
                $routes['callback'],
                '\Vizir\KeycloakWebGuard\Controllers\AuthController@callback'
            )->name('callback');
        }
    }
}
