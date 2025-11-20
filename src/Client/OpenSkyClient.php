<?php

namespace OpenSky\Laravel\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use OpenSky\Laravel\DTOs\StateVectorResponse;
use OpenSky\Laravel\DTOs\FlightResponse;
use OpenSky\Laravel\DTOs\TrackResponse;
use OpenSky\Laravel\Exceptions\OpenSkyException;
use Psr\SimpleCache\CacheInterface;

class OpenSkyClient
{
    private ClientInterface $httpClient;
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

    private ?CacheInterface $cache;
    private int $cacheTtl;
    private string $cachePrefix;

    /**
     * Create a new OpenSky API client instance.
     *
     * @param string $baseUrl The base URL for the OpenSky API
     * @param string|null $username Legacy basic auth username (being deprecated)
     * @param string|null $password Legacy basic auth password (being deprecated)
     * @param int $timeout Request timeout in seconds
     * @param string|null $clientId OAuth2 client ID (recommended)
     * @param string|null $clientSecret OAuth2 client secret (recommended)
     * @param string|null $oauthTokenUrl OAuth2 token endpoint URL
     * @param \GuzzleHttp\ClientInterface|null $httpClient Optional HTTP client instance
     * @param \Psr\SimpleCache\CacheInterface|null $cache Optional PSR-16 cache instance
     * @param int $cacheTtl Cache TTL in seconds (default: 60)
     * @param string $cachePrefix Cache key prefix (default: 'opensky:')
     */
    public function __construct(
        string $baseUrl = 'https://opensky-network.org/api',
        ?string $username = null,
        ?string $password = null,
        int $timeout = 30,
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $oauthTokenUrl = null,
        ?ClientInterface $httpClient = null,
        ?CacheInterface $cache = null,
        int $cacheTtl = 60,
        string $cachePrefix = 'opensky:'
    ) {
        $this->baseUrl = rtrim($baseUrl, '/') . '/'; // Ensure trailing slash
        $this->username = $username;
        $this->password = $password;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->oauthTokenUrl = $oauthTokenUrl ?? 'https://auth.opensky-network.org/auth/realms/opensky-network/protocol/openid-connect/token';
        $this->timeout = $timeout;
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl;
        $this->cachePrefix = $cachePrefix;

        if ($httpClient) {
            $this->httpClient = $httpClient;
        } else {
            $this->httpClient = new Client([
                'base_uri' => $this->baseUrl,
                'timeout' => $this->timeout,
                'headers' => [
                    'User-Agent' => 'Laravel OpenSky Package/1.0 (Guzzle)',
                    'Accept' => 'application/json',
                ],
            ]);
        }
    }

    /**
     * Retrieve state vectors for all aircraft or filter by various criteria.
     * 
     * This endpoint provides real-time information about aircraft positions,
     * velocities, and other flight data. Anonymous users are limited to 400 
     * credits per day, authenticated users get 4000 credits per day.
     *
     * @param int|null $time Unix timestamp to retrieve historical data (max 1 hour ago for anonymous users)
     * @param array|null $icao24 Array of ICAO24 transponder addresses to filter by
     * @param float|null $lamin Lower bound for latitude (WGS-84, decimal degrees)
     * @param float|null $lomin Lower bound for longitude (WGS-84, decimal degrees)
     * @param float|null $lamax Upper bound for latitude (WGS-84, decimal degrees)
     * @param float|null $lomax Upper bound for longitude (WGS-84, decimal degrees)
     * @param int|null $extended Set to 1 to retrieve additional data fields
     * @return StateVectorResponse Collection of state vectors with metadata
     * @throws OpenSkyException When the API request fails or returns an error
     */
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

        $response = $this->makeRequest('states/all', $params);

        return StateVectorResponse::fromArray($response);
    }

    /**
     * Retrieve state vectors for aircraft registered to your account.
     * 
     * This endpoint is only available for authenticated users and returns
     * state vectors for aircraft that you have registered ownership of.
     * Requires authentication via OAuth2 or basic auth.
     *
     * @param array|null $serials Array of sensor serial numbers to filter by
     * @return StateVectorResponse Collection of state vectors for owned aircraft
     * @throws OpenSkyException When authentication is required or API request fails
     */
    public function getOwnStateVectors(?array $serials = null): StateVectorResponse
    {
        $this->requireAuthentication();

        $params = array_filter([
            'serials' => $serials,
        ], fn($value) => $value !== null);

        $response = $this->makeRequest('states/own', $params);

        return StateVectorResponse::fromArray($response);
    }

    /**
     * Retrieve flights that departed or arrived within a specific time interval.
     * 
     * Returns all flights that were active during the specified time period.
     * The OpenSky API enforces a maximum time interval of 2 hours for this endpoint.
     *
     * @param int $begin Unix timestamp for the start of the time interval
     * @param int $end Unix timestamp for the end of the time interval (max 2 hours after begin, as per OpenSky API)
     * @return FlightResponse Collection of flights within the time interval
     * @throws OpenSkyException When time interval exceeds 2 hours (OpenSky API limitation) or API request fails
     */
    public function getFlightsInTimeInterval(int $begin, int $end): FlightResponse
    {
        if ($end - $begin > 7200) {
            throw OpenSkyException::invalidTimeInterval('2 hours');
        }

        $response = $this->makeRequest('flights/all', [
            'begin' => $begin,
            'end' => $end,
        ]);

        return FlightResponse::fromArray($response);
    }

    /**
     * Retrieve flights for a particular aircraft within a time interval.
     * 
     * Returns all flights performed by the specified aircraft during the given
     * time period. The time interval is limited to a maximum of 30 days.
     *
     * @param string $icao24 ICAO24 transponder address of the aircraft (case-insensitive)
     * @param int $begin Unix timestamp for the start of the time interval
     * @param int $end Unix timestamp for the end of the time interval (max 30 days after begin)
     * @return FlightResponse Collection of flights for the specified aircraft
     * @throws OpenSkyException When time interval exceeds 30 days or API request fails
     */
    public function getFlightsByAircraft(string $icao24, int $begin, int $end): FlightResponse
    {
        if ($end - $begin > 2592000) {
            throw OpenSkyException::invalidTimeInterval('30 days');
        }

        $response = $this->makeRequest('flights/aircraft', [
            'icao24' => strtolower($icao24),
            'begin' => $begin,
            'end' => $end,
        ]);

        return FlightResponse::fromArray($response);
    }

    /**
     * Retrieve flights that arrived at a specific airport within a time interval.
     * 
     * Returns all flights that landed at the specified airport during the given
     * time period. The OpenSky API enforces a maximum time interval of 7 days for this endpoint.
     *
     * @param string $airport ICAO airport code (e.g., 'EDDF' for Frankfurt)
     * @param int $begin Unix timestamp for the start of the time interval
     * @param int $end Unix timestamp for the end of the time interval (max 7 days after begin, as per OpenSky API)
     * @return FlightResponse Collection of arriving flights at the airport
     * @throws OpenSkyException When time interval exceeds 7 days (OpenSky API limitation) or API request fails
     */
    public function getArrivalsByAirport(string $airport, int $begin, int $end): FlightResponse
    {
        if ($end - $begin > 604800) {
            throw OpenSkyException::invalidTimeInterval('7 days');
        }

        $response = $this->makeRequest('flights/arrival', [
            'airport' => $airport,
            'begin' => $begin,
            'end' => $end,
        ]);

        return FlightResponse::fromArray($response);
    }

    /**
     * Retrieve flights that departed from a specific airport within a time interval.
     * 
     * Returns all flights that took off from the specified airport during the given
     * time period. The OpenSky API enforces a maximum time interval of 7 days for this endpoint.
     *
     * @param string $airport ICAO airport code (e.g., 'EDDF' for Frankfurt)
     * @param int $begin Unix timestamp for the start of the time interval
     * @param int $end Unix timestamp for the end of the time interval (max 7 days after begin, as per OpenSky API)
     * @return FlightResponse Collection of departing flights from the airport
     * @throws OpenSkyException When time interval exceeds 7 days (OpenSky API limitation) or API request fails
     */
    public function getDeparturesByAirport(string $airport, int $begin, int $end): FlightResponse
    {
        if ($end - $begin > 604800) {
            throw OpenSkyException::invalidTimeInterval('7 days');
        }

        $response = $this->makeRequest('flights/departure', [
            'airport' => $airport,
            'begin' => $begin,
            'end' => $end,
        ]);

        return FlightResponse::fromArray($response);
    }

    /**
     * Retrieve the trajectory (track) of a specific aircraft.
     * 
     * Returns waypoints along the flight path of the aircraft. If no time is
     * specified, returns the most recent track. The track data includes
     * position, altitude, velocity, and timestamp information.
     *
     * @param string $icao24 ICAO24 transponder address of the aircraft (case-insensitive)
     * @param int $time Unix timestamp to retrieve track at a specific time (0 for most recent)
     * @return TrackResponse The flight track with waypoints and metadata
     * @throws OpenSkyException When the API request fails or no track data is available
     */
    public function getTrackByAircraft(string $icao24, int $time = 0): TrackResponse
    {
        $response = $this->makeRequest('tracks', [
            'icao24' => strtolower($icao24),
            'time' => $time,
        ]);

        return TrackResponse::fromArray($response);
    }

    private function makeRequest(string $endpoint, array $params = []): array
    {
        $cacheKey = $this->getCacheKey($endpoint, $params);

        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);

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
                $options['query'] = $params;
            }

            $response = $this->httpClient->request('GET', $endpoint, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            if ($this->cache) {
                $this->cache->set($cacheKey, $data, $this->cacheTtl);
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

    private function getCacheKey(string $endpoint, array $params): string
    {
        $hash = md5($endpoint . serialize($params));

        return $this->cachePrefix . $hash;
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

        // Check cache for existing token
        $cacheKey = 'opensky_oauth_token_' . md5($this->clientId);
        if ($this->cache) {
            $cachedToken = $this->cache->get($cacheKey);

            if ($cachedToken) {
                $this->accessToken = $cachedToken;
                return $this->accessToken;
            }
        }

        // Request new token
        try {
            $response = $this->httpClient->request('POST', $this->oauthTokenUrl, [
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
                // Add more detailed error information
                throw OpenSkyException::oauthTokenFailed(
                    'No access token received. Response: ' . json_encode($data)
                );
            }

            // Cache the token for slightly less than its expiry time to avoid edge cases
            if ($this->cache) {
                $cacheTtl = max(60, $expiresIn - 300); // Cache for expiry - 5 minutes, minimum 1 minute
                $this->cache->set($cacheKey, $this->accessToken, $cacheTtl);
            }

            return $this->accessToken;
        } catch (GuzzleException $e) {
            throw OpenSkyException::oauthTokenFailed($e->getMessage());
        }
    }
}