<?php

namespace OpenSky\Laravel;

use Illuminate\Support\ServiceProvider;
use OpenSky\Laravel\Client\OpenSkyClient;
use OpenSky\Laravel\Client\OpenSkyConfig;

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

            $cache = null;
            if ($config['cache']['enabled'] ?? true) {
                $cache = $app['cache']->store($config['cache']['store'] ?? null);
            }

            $clientConfig = new OpenSkyConfig(
                baseUrl: $config['base_url'],
                timeout: $config['timeout'] ?? 30,
                username: $config['username'] ?? null,
                password: $config['password'] ?? null,
                clientId: $config['client_id'] ?? null,
                clientSecret: $config['client_secret'] ?? null,
                oauthTokenUrl: $config['oauth_token_url'] ?? null,
                cacheTtl: $config['cache']['ttl'] ?? 60,
                cachePrefix: $config['cache']['prefix'] ?? 'opensky:'
            );

            return new OpenSkyClient(
                config: $clientConfig,
                httpClient: null,
                cache: $cache
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