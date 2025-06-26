<?php

namespace OpenSky\Laravel;

use Illuminate\Support\ServiceProvider;
use OpenSky\Laravel\Client\OpenSkyClient;

class OpenSkyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/opensky.php',
            'opensky'
        );

        $this->app->singleton(OpenSkyClient::class, function ($app) {
            $config = $app['config']['opensky'];
            
            return new OpenSkyClient(
                baseUrl: $config['base_url'],
                username: $config['username'] ?? null,
                password: $config['password'] ?? null,
                timeout: $config['timeout'] ?? 30,
                clientId: $config['client_id'] ?? null,
                clientSecret: $config['client_secret'] ?? null,
                oauthTokenUrl: $config['oauth_token_url'] ?? null
            );
        });

        $this->app->alias(OpenSkyClient::class, 'opensky');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/opensky.php' => config_path('opensky.php'),
            ], 'opensky-config');
        }
    }
} 