<?php

namespace Vizir\KeycloakWebGuard;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Vizir\KeycloakWebGuard\Auth\Guard\KeycloakWebGuard;
use Vizir\KeycloakWebGuard\Auth\KeycloakWebUserProvider;
use Vizir\KeycloakWebGuard\Services\KeycloakService;

class KeycloakWebGuardServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Configuration
        $config = __DIR__ . '/../config/keycloak-web.php';

        $this->publishes([$config => config_path('keycloak-web.php')], 'config');
        $this->mergeConfigFrom($config, 'keycloak-web');

        // User Provider
        Auth::provider('keycloak-users', function($app, array $config) {
            return new KeycloakWebUserProvider($app['hash'], $config['model']);
        });
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Keycloak Web Guard
        Auth::extend('keycloak-web', function ($app, $name, array $config) {
            $provider = Auth::createUserProvider($config['provider']);
            return new KeycloakWebGuard($provider, $app->request);
        });

        // Facades
        $this->app->bind('keycloak-web', function($app) {
            return $app->make(KeycloakService::class);
        });

        // Bind for client data
        $this->app->when(KeycloakService::class)->needs(ClientInterface::class)->give(function() {
            return new Client(Config::get('keycloak-web.guzzle_options', []));
        });
    }
}
