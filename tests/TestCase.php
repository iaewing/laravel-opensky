<?php

namespace OpenSky\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use OpenSky\Laravel\OpenSkyServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            OpenSkyServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Set up test environment
        $app['config']->set('opensky.cache.enabled', false); // Disable cache for tests
        $app['config']->set('cache.default', 'array');
    }
} 