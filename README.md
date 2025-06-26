# Laravel OpenSky Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/iaewing/laravel-opensky.svg?style=flat-square)](https://packagist.org/packages/iaewing/laravel-opensky)
[![Total Downloads](https://img.shields.io/packagist/dt/iaewing/laravel-opensky.svg?style=flat-square)](https://packagist.org/packages/iaewing/laravel-opensky)

A Laravel package for easy integration with the [OpenSky Network API](https://openskynetwork.github.io/opensky-api/rest.html). This package provides a simple and elegant way to access real-time and historical aviation data.

**Note**: This package is for research and non-commercial purposes only, as per OpenSky Network's terms of use. For commercial usage, contact OpenSky Network directly.

## Features

- **Complete API Coverage**: Supports all OpenSky Network REST API endpoints
- **Laravel Integration**: Native Laravel service provider and facade
- **Type-Safe DTOs**: Strongly typed data transfer objects for all API responses
- **Caching Support**: Built-in response caching to reduce API calls
- **Rate Limiting**: Respects OpenSky API rate limits
- **Authentication**: Support for both anonymous and authenticated requests
- **Comprehensive Testing**: Full test coverage with PHPUnit

## Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 9.x or higher

## Installation

Install the package via Composer:

```bash
composer require iaewing/laravel-opensky
```

The package will automatically register its service provider in Laravel 9+.

Optionally, publish the configuration file to customize cache settings, timeouts, or other options:

```bash
php artisan vendor:publish --tag=opensky-config
```

**Note**: Publishing the config is optional. The package works with default settings using only environment variables.

## Configuration

Add your OpenSky credentials to your `.env` file. OpenSky supports two authentication methods:

### Option 1: OAuth2 Client Credentials (Recommended)
```env
OPENSKY_CLIENT_ID=your_client_id
OPENSKY_CLIENT_SECRET=your_client_secret
OPENSKY_CACHE_ENABLED=true
OPENSKY_CACHE_TTL=60
```

### Option 2: Legacy Basic Authentication (Being Deprecated)
```env
OPENSKY_USERNAME=your_username
OPENSKY_PASSWORD=your_password
OPENSKY_CACHE_ENABLED=true
OPENSKY_CACHE_TTL=60
```

**Note**: For new accounts created after March 2025, you must use OAuth2. Legacy basic authentication only works for older accounts.

## Authentication Methods

The OpenSky Network API supports two authentication methods:

### 1. OAuth2 Client Credentials Flow (Recommended)

This is the modern, secure authentication method required for all new accounts created after March 2025:

1. Log in to your OpenSky account at https://opensky-network.org/
2. Visit the Account page and create a new API client
3. Retrieve your `client_id` and `client_secret`
4. Add them to your `.env` file:

```env
OPENSKY_CLIENT_ID=your_client_id
OPENSKY_CLIENT_SECRET=your_client_secret
```

### 2. Legacy Basic Authentication (Being Deprecated)

This method uses your OpenSky username and password directly. It only works for legacy accounts created before March 2025:

```env
OPENSKY_USERNAME=your_opensky_username
OPENSKY_PASSWORD=your_opensky_password
```

**Important Notes:**
- The username/password refer to your OpenSky Network account credentials (not just any random credentials)
- New accounts must use OAuth2
- Legacy basic auth is being phased out
- You need an OpenSky Network account to access authenticated endpoints
- The package automatically prefers OAuth2 over basic auth when both are configured

### Anonymous Access

Some endpoints work without authentication but have stricter rate limits:
- Only current data (no historical data)
- 400 API credits per day
- 10-second resolution instead of 5-second

## Licensing and Terms of Use

This package respects OpenSky Network's terms of use:

- **Research & Non-Commercial Use**: Free with API rate limits
- **Commercial Use**: Requires separate licensing from OpenSky Network
- **Attribution Required**: When publishing research, cite the OpenSky paper:
  
  > Matthias Schäfer, Martin Strohmeier, Vincent Lenders, Ivan Martinovic and Matthias Wilhelm.
  > "Bringing Up OpenSky: A Large-scale ADS-B Sensor Network for Research".
  > In Proceedings of the 13th IEEE/ACM International Symposium on Information Processing in Sensor Networks (IPSN), pages 83-94, April 2014.

For commercial usage or higher rate limits, contact OpenSky Network directly at https://opensky-network.org/

## Usage

### Basic Usage with Facade

```php
use OpenSky\Laravel\Facades\OpenSky;

// Get all current state vectors
$states = OpenSky::getAllStateVectors();

// Get state vectors in a specific area (bounding box)
$states = OpenSky::getAllStateVectors(
    lamin: 45.8389,  // Switzerland
    lomin: 5.9962,
    lamax: 47.8229,
    lomax: 10.5226
);

// Get flights in a time interval (max 1 hour)
$flights = OpenSky::getFlightsInTimeInterval(
    begin: now()->subHour()->timestamp,
    end: now()->timestamp
);
```

### Dependency Injection

```php
use OpenSky\Laravel\Client\OpenSkyClient;

class FlightController extends Controller
{
    public function __construct(private OpenSkyClient $openSky)
    {
    }

    public function index()
    {
        $states = $this->openSky->getAllStateVectors();
        
        return view('flights.index', compact('states'));
    }
}
```

## Available Methods

### State Vectors

```php
// Get all state vectors (rate limited for anonymous users)
$states = OpenSky::getAllStateVectors(?int $time, ?array $icao24, ?float $lamin, ?float $lomin, ?float $lamax, ?float $lomax, ?int $extended);

// Get your own state vectors (requires authentication)
$states = OpenSky::getOwnStateVectors(?array $serials);
```

### Flights

```php
// Get flights in time interval (max 1 hour)
$flights = OpenSky::getFlightsInTimeInterval(int $begin, int $end);

// Get flights by aircraft (max 30 days)
$flights = OpenSky::getFlightsByAircraft(string $icao24, int $begin, int $end);

// Get arrivals by airport (max 7 days)
$flights = OpenSky::getArrivalsByAirport(string $airport, int $begin, int $end);

// Get departures by airport (max 7 days)
$flights = OpenSky::getDeparturesByAirport(string $airport, int $begin, int $end);
```

### Tracks

```php
// Get track by aircraft
$track = OpenSky::getTrackByAircraft(string $icao24, int $time = 0);
```

## Data Transfer Objects

The package returns strongly typed DTOs for all API responses:

### StateVectorResponse

```php
$states = OpenSky::getAllStateVectors();

echo $states->time; // Unix timestamp
foreach ($states->states as $state) {
    echo $state->icao24;        // Aircraft identifier
    echo $state->callsign;      // Flight callsign
    echo $state->originCountry; // Country of origin
    echo $state->latitude;      // Current latitude
    echo $state->longitude;     // Current longitude
    echo $state->baroAltitude;  // Barometric altitude
    echo $state->velocity;      // Ground speed
    echo $state->onGround;      // Is aircraft on ground
    // ... and more properties
}
```

### FlightResponse

```php
$flights = OpenSky::getFlightsInTimeInterval($begin, $end);

foreach ($flights->flights as $flight) {
    echo $flight->icao24;
    echo $flight->callsign;
    echo $flight->estDepartureAirport;
    echo $flight->estArrivalAirport;
    echo $flight->firstSeen;
    echo $flight->lastSeen;
}
```

### TrackResponse

```php
$track = OpenSky::getTrackByAircraft('3c4b26');

echo $track->icao24;
echo $track->callsign;
echo $track->startTime;
echo $track->endTime;

foreach ($track->path as $waypoint) {
    echo $waypoint->time;
    echo $waypoint->latitude;
    echo $waypoint->longitude;
    echo $waypoint->baroAltitude;
    echo $waypoint->trueTrack;
    echo $waypoint->onGround;
}
```

## Examples

### Real-time Flight Tracking

```php
// Get all aircraft currently over Germany
$states = OpenSky::getAllStateVectors(
    lamin: 47.3024,
    lomin: 5.8662,
    lamax: 55.0581,
    lomax: 15.0419
);

foreach ($states->states as $aircraft) {
    if (!$aircraft->onGround && $aircraft->velocity > 100) {
        echo "Flight {$aircraft->callsign} at {$aircraft->baroAltitude}m altitude\n";
    }
}
```

### Airport Traffic Analysis

```php
// Get all departures from Frankfurt Airport in the last hour
$flights = OpenSky::getDeparturesByAirport(
    'EDDF',
    now()->subHour()->timestamp,
    now()->timestamp
);

$flightCount = $flights->flights->count();
echo "Frankfurt had {$flightCount} departures in the last hour\n";
```

### Aircraft Route Tracking

```php
// Track a specific aircraft's route
$track = OpenSky::getTrackByAircraft('3c4b26', now()->subHour()->timestamp);

$route = $track->path->map(function ($waypoint) {
    return [
        'lat' => $waypoint->latitude,
        'lng' => $waypoint->longitude,
        'alt' => $waypoint->baroAltitude,
        'time' => date('H:i:s', $waypoint->time)
    ];
});

// Use $route for map visualization
```

## Configuration Options

After publishing the config file (`php artisan vendor:publish --tag=opensky-config`), you can customize these settings in `config/opensky.php`:

```php
return [
    // API endpoint
    'base_url' => env('OPENSKY_BASE_URL', 'https://opensky-network.org/api'),
    
    // Authentication (set in .env file)
    'username' => env('OPENSKY_USERNAME'),
    'password' => env('OPENSKY_PASSWORD'),
    'client_id' => env('OPENSKY_CLIENT_ID'),
    'client_secret' => env('OPENSKY_CLIENT_SECRET'),
    'oauth_token_url' => env('OPENSKY_OAUTH_TOKEN_URL', 'https://auth.opensky-network.org/auth/realms/opensky-network/protocol/openid-connect/token'),
    
    // Request timeout
    'timeout' => env('OPENSKY_TIMEOUT', 30),
    
    // Rate limiting
    'rate_limit' => [
        'enabled' => env('OPENSKY_RATE_LIMIT_ENABLED', true),
        'anonymous_per_day' => 400,
        'authenticated_per_day' => 4000,
        'active_feeder_per_day' => 8000,
    ],
    
    // Response caching
    'cache' => [
        'enabled' => env('OPENSKY_CACHE_ENABLED', true),
        'ttl' => env('OPENSKY_CACHE_TTL', 60), // seconds
        'store' => env('OPENSKY_CACHE_STORE', 'default'),
        'prefix' => 'opensky:',
    ],
];
```

## Rate Limiting

The OpenSky API has different rate limits based on API credits:

- **Anonymous users**: 400 API credits per day
- **Authenticated users**: 4000 API credits per day  
- **Active feeders**: 8000 API credits per day

Credit usage varies by request:
- Small areas (0-25 sq deg): 1 credit
- Medium areas (25-100 sq deg): 2 credits  
- Large areas (100-400 sq deg): 3 credits
- Global requests (>400 sq deg): 4 credits

The package respects these limits and provides caching to reduce API calls.

## Error Handling

The package throws `OpenSkyException` for API errors:

```php
use OpenSky\Laravel\Exceptions\OpenSkyException;

try {
    $states = OpenSky::getAllStateVectors();
} catch (OpenSkyException $e) {
    Log::error('OpenSky API error: ' . $e->getMessage());
}
```

## Testing

This package uses [Pest](https://pestphp.com) for testing:

```bash
composer test

# Run tests with coverage
./vendor/bin/pest --coverage

# Run specific test file
./vendor/bin/pest tests/Unit/OpenSkyClientTest.php

# Run with verbose output
./vendor/bin/pest --verbose
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## Credits

- Built for the [OpenSky Network](https://opensky-network.org/) API
- Inspired by the need for easy aviation data access in Laravel applications

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## OAuth Token Caching

When using OAuth2 authentication, the package automatically caches access tokens to improve performance:

- **Token Lifetime**: OAuth2 tokens are valid for 30 minutes
- **Cache Duration**: Tokens are cached for 25 minutes (5 minutes before expiry)
- **Automatic Refresh**: New tokens are automatically requested when the cached token expires
- **Cache Key**: Uses a hash of your client_id to ensure uniqueness
- **Cache Store**: Uses the same cache store configured for API responses

This means you won't need to authenticate on every API call, significantly improving performance for applications making frequent requests. 