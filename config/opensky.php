<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenSky API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the OpenSky Network API client.
    | You can obtain credentials by registering at https://opensky-network.org/
    |
    */

    'base_url' => env('OPENSKY_BASE_URL', 'https://opensky-network.org/api'),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | OpenSky supports two authentication methods:
    | 1. Legacy Basic Auth (username/password) - being deprecated
    | 2. OAuth2 Client Credentials Flow (recommended)
    |
    | For OAuth2, set client_id and client_secret instead of username/password
    |
    */

    'username' => env('OPENSKY_USERNAME'),
    'password' => env('OPENSKY_PASSWORD'),
    
    'client_id' => env('OPENSKY_CLIENT_ID'),
    'client_secret' => env('OPENSKY_CLIENT_SECRET'),
    'oauth_token_url' => env('OPENSKY_OAUTH_TOKEN_URL', 'https://auth.opensky-network.org/auth/realms/opensky-network/protocol/openid-connect/token'),

    'timeout' => env('OPENSKY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for respecting OpenSky API rate limits.
    | Anonymous users: 400 API credits per day
    | Authenticated users: 4000 API credits per day
    | Active feeders: 8000 API credits per day
    |
    */

    'rate_limit' => [
        'enabled' => env('OPENSKY_RATE_LIMIT_ENABLED', true),
        'anonymous_per_day' => 400,
        'authenticated_per_day' => 4000,
        'active_feeder_per_day' => 8000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for API responses to reduce API calls
    |
    */

    'cache' => [
        'enabled' => env('OPENSKY_CACHE_ENABLED', true),
        'ttl' => env('OPENSKY_CACHE_TTL', 60), // seconds
        'store' => env('OPENSKY_CACHE_STORE', 'default'),
        'prefix' => 'opensky:',
    ],
]; 