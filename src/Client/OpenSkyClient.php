<?php

namespace OpenSky\Laravel\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OpenSky\Laravel\DTOs\StateVectorResponse;
use OpenSky\Laravel\DTOs\FlightResponse;
use OpenSky\Laravel\DTOs\TrackResponse;
use OpenSky\Laravel\Exceptions\OpenSkyException;
use Illuminate\Support\Facades\Cache;

class OpenSkyClient
{
    private Client $httpClient;
    private string $baseUrl;
    // Legacy basic authentication (being deprecated)
    private ?string $username;
    private ?string $password;
    
    // OAuth2 client credentials (recommended)
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $oauthTokenUrl;
    private int $timeout;
    private ?string $accessToken = null;

    public function __construct(
        string $baseUrl = 'https://opensky-network.org/api',
        ?string $username = null,
        ?string $password = null,
        int $timeout = 30,
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $oauthTokenUrl = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->oauthTokenUrl = $oauthTokenUrl ?? 'https://auth.opensky-network.org/auth/realms/opensky-network/protocol/openid-connect/token';
        $this->timeout = $timeout;

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
        ]);
    }

    public function getAllStateVectors(
        ?int $time = null,
        ?array $icao24 = null,
        ?float $lamin = null,
        ?float $lomin = null,
        ?float $lamax = null,
        ?float $lomax = null,
        ?int $extended = null
    ): StateVectorResponse {
        $params = array_filter([
            'time' => $time,
            'icao24' => $icao24,
            'lamin' => $lamin,
            'lomin' => $lomin,
            'lamax' => $lamax,
            'lomax' => $lomax,
            'extended' => $extended,
        ], fn($value) => $value !== null);

        $response = $this->makeRequest('GET', '/states/all', $params);
        
        return StateVectorResponse::fromArray($response);
    }

    public function getOwnStateVectors(?array $serials = null): StateVectorResponse
    {
        $this->requireAuthentication();

        $params = array_filter([
            'serials' => $serials,
        ], fn($value) => $value !== null);

        $response = $this->makeRequest('GET', '/states/own', $params);
        
        return StateVectorResponse::fromArray($response);
    }

    public function getFlightsInTimeInterval(int $begin, int $end): FlightResponse
    {
        if ($end - $begin > 3600) {
            throw OpenSkyException::invalidTimeInterval('1 hour');
        }

        $response = $this->makeRequest('GET', '/flights/all', [
            'begin' => $begin,
            'end' => $end,
        ]);

        return FlightResponse::fromArray($response);
    }

    public function getFlightsByAircraft(string $icao24, int $begin, int $end): FlightResponse
    {
        if ($end - $begin > 2592000) {
            throw OpenSkyException::invalidTimeInterval('30 days');
        }

        $response = $this->makeRequest('GET', '/flights/aircraft', [
            'icao24' => strtolower($icao24),
            'begin' => $begin,
            'end' => $end,
        ]);

        return FlightResponse::fromArray($response);
    }

    public function getArrivalsByAirport(string $airport, int $begin, int $end): FlightResponse
    {
        if ($end - $begin > 604800) {
            throw OpenSkyException::invalidTimeInterval('7 days');
        }

        $response = $this->makeRequest('GET', '/flights/arrival', [
            'airport' => $airport,
            'begin' => $begin,
            'end' => $end,
        ]);

        return FlightResponse::fromArray($response);
    }

    public function getDeparturesByAirport(string $airport, int $begin, int $end): FlightResponse
    {
        if ($end - $begin > 604800) {
            throw OpenSkyException::invalidTimeInterval('7 days');
        }

        $response = $this->makeRequest('GET', '/flights/departure', [
            'airport' => $airport,
            'begin' => $begin,
            'end' => $end,
        ]);

        return FlightResponse::fromArray($response);
    }

    public function getTrackByAircraft(string $icao24, int $time = 0): TrackResponse
    {
        $response = $this->makeRequest('GET', '/tracks', [
            'icao24' => strtolower($icao24),
            'time' => $time,
        ]);

        return TrackResponse::fromArray($response);
    }

    private function makeRequest(string $method, string $endpoint, array $params = []): array
    {
        $cacheKey = $this->getCacheKey($method, $endpoint, $params);
        
        if (config('opensky.cache.enabled', true)) {
            $cached = Cache::store(config('opensky.cache.store', 'default'))
                ->get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $options = [];
            
            // Handle authentication - prefer OAuth2 over basic auth
            if ($this->clientId && $this->clientSecret) {
                $accessToken = $this->getAccessToken();
                $options['headers']['Authorization'] = "Bearer {$accessToken}";
            } elseif ($this->username && $this->password) {
                $options['auth'] = [$this->username, $this->password];
            }

            if (!empty($params)) {
                if ($method === 'GET') {
                    $options['query'] = $params;
                } else {
                    $options['json'] = $params;
                }
            }

            $response = $this->httpClient->request($method, $endpoint, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            if (config('opensky.cache.enabled', true)) {
                Cache::store(config('opensky.cache.store', 'default'))
                    ->put($cacheKey, $data, config('opensky.cache.ttl', 60));
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new OpenSkyException(
                "API request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    private function getCacheKey(string $method, string $endpoint, array $params): string
    {
        $prefix = config('opensky.cache.prefix', 'opensky:');
        $hash = md5($method . $endpoint . serialize($params));
        
        return $prefix . $hash;
    }

    private function requireAuthentication(): void
    {
        if ((!$this->username || !$this->password) && (!$this->clientId || !$this->clientSecret)) {
            throw OpenSkyException::authenticationRequired();
        }
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        if (!$this->clientId || !$this->clientSecret) {
            throw OpenSkyException::authenticationRequired();
        }

        // Check Laravel cache for existing token
        $cacheKey = 'opensky_oauth_token_' . md5($this->clientId);
        if (config('opensky.cache.enabled', true)) {
            $cachedToken = Cache::store(config('opensky.cache.store', 'default'))
                ->get($cacheKey);
            
            if ($cachedToken) {
                $this->accessToken = $cachedToken;
                return $this->accessToken;
            }
        }

        // Request new token
        try {
            $response = $this->httpClient->post($this->oauthTokenUrl, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->accessToken = $data['access_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? 1800; // Default to 30 minutes

            if (!$this->accessToken) {
                throw OpenSkyException::oauthTokenFailed('No access token received');
            }

            // Cache the token for slightly less than its expiry time to avoid edge cases
            if (config('opensky.cache.enabled', true)) {
                $cacheTtl = max(60, $expiresIn - 300); // Cache for expiry - 5 minutes, minimum 1 minute
                Cache::store(config('opensky.cache.store', 'default'))
                    ->put($cacheKey, $this->accessToken, $cacheTtl);
            }

            return $this->accessToken;
        } catch (GuzzleException $e) {
            throw OpenSkyException::oauthTokenFailed($e->getMessage());
        }
    }
} 