<?php

namespace OpenSky\Laravel\Client;

class OpenSkyConfig
{
    public readonly string $baseUrl;

    public function __construct(
        string $baseUrl = 'https://opensky-network.org/api',
        public readonly int $timeout = 30,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly ?string $clientId = null,
        public readonly ?string $clientSecret = null,
        public readonly ?string $oauthTokenUrl = null,
        public readonly int $cacheTtl = 60,
        public readonly string $cachePrefix = 'opensky:',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
    }
}
