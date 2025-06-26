<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenSky\Laravel\Client\OpenSkyClient;
use OpenSky\Laravel\DTOs\StateVectorResponse;
use OpenSky\Laravel\DTOs\FlightResponse;
use OpenSky\Laravel\DTOs\TrackResponse;
use OpenSky\Laravel\Exceptions\OpenSkyException;

function mockClient(array $responses): OpenSkyClient
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);
    $httpClient = new Client(['handler' => $handlerStack]);

    $client = new OpenSkyClient();
    
    $reflection = new ReflectionClass($client);
    $property = $reflection->getProperty('httpClient');
    $property->setAccessible(true);
    $property->setValue($client, $httpClient);

    return $client;
}

it('can get all state vectors', function () {
    $responseData = [
        'time' => 1517184000,
        'states' => [
            [
                '3c4b26',
                'D-ABYF  ',
                'Germany',
                1517183998,
                1517184000,
                9.5136,
                48.7467,
                10972.8,
                false,
                157.94,
                108.52,
                null,
                null,
                11582.4,
                '1000',
                false,
                0,
                0
            ]
        ]
    ];

    $client = mockClient([
        new Response(200, [], json_encode($responseData))
    ]);

    $response = $client->getAllStateVectors();

    expect($response)
        ->toBeInstanceOf(StateVectorResponse::class)
        ->and($response->time)->toBe(1517184000)
        ->and($response->states)->toHaveCount(1)
        ->and($response->states->first()->icao24)->toBe('3c4b26')
        ->and($response->states->first()->callsign)->toBe('D-ABYF');
});

it('can get flights in time interval', function () {
    $responseData = [
        [
            'icao24' => '3c4b26',
            'firstSeen' => 1517184000,
            'estDepartureAirport' => 'EDDF',
            'lastSeen' => 1517190000,
            'estArrivalAirport' => 'LFPG',
            'callsign' => 'DLH441  ',
            'estDepartureAirportHorizDistance' => 100,
            'estDepartureAirportVertDistance' => 50,
            'estArrivalAirportHorizDistance' => 200,
            'estArrivalAirportVertDistance' => 75,
            'departureAirportCandidatesCount' => 1,
            'arrivalAirportCandidatesCount' => 1
        ]
    ];

    $client = mockClient([
        new Response(200, [], json_encode($responseData))
    ]);

    $response = $client->getFlightsInTimeInterval(1517188000, 1517190000);

    expect($response)
        ->toBeInstanceOf(FlightResponse::class)
        ->and($response->flights)->toHaveCount(1)
        ->and($response->flights->first()->icao24)->toBe('3c4b26')
        ->and($response->flights->first()->callsign)->toBe('DLH441');
});

it('can get track by aircraft', function () {
    $responseData = [
        'icao24' => '3c4b26',
        'startTime' => 1517184000,
        'endTime' => 1517190000,
        'callsign' => 'DLH441  ',
        'path' => [
            [1517184000, 50.0379, 8.5622, 180.0, 90.0, false],
            [1517184060, 50.0389, 8.5632, 200.0, 95.0, false]
        ]
    ];

    $client = mockClient([
        new Response(200, [], json_encode($responseData))
    ]);

    $response = $client->getTrackByAircraft('3c4b26', 1517184000);

    expect($response)
        ->toBeInstanceOf(TrackResponse::class)
        ->and($response->icao24)->toBe('3c4b26')
        ->and($response->callsign)->toBe('DLH441')
        ->and($response->path)->toHaveCount(2);
});

it('validates time interval for flights', function () {
    $client = new OpenSkyClient();

    expect(fn() => $client->getFlightsInTimeInterval(0, 3601))
        ->toThrow(OpenSkyException::class, 'Time interval must not be larger than 1 hour');
});

it('validates time interval for aircraft flights', function () {
    $client = new OpenSkyClient();

    expect(fn() => $client->getFlightsByAircraft('3c4b26', 0, 2592001))
        ->toThrow(OpenSkyException::class, 'Time interval must not be larger than 30 days');
});

it('validates time interval for airport arrivals', function () {
    $client = new OpenSkyClient();

    expect(fn() => $client->getArrivalsByAirport('EDDF', 0, 604801))
        ->toThrow(OpenSkyException::class, 'Time interval must not be larger than 7 days');
});

it('requires authentication for own state vectors', function () {
    $client = new OpenSkyClient();

    expect(fn() => $client->getOwnStateVectors())
        ->toThrow(OpenSkyException::class, 'This endpoint requires authentication. Please provide either username/password or OAuth2 credentials.');
});

it('can authenticate with oauth2', function () {
    // Mock OAuth token response
    $tokenResponse = [
        'access_token' => 'mock_access_token_12345',
        'expires_in' => 1800,
        'token_type' => 'Bearer'
    ];

    // Mock state vectors response
    $stateResponse = [
        'time' => 1517184000,
        'states' => [
            [
                '3c4b26',
                'D-ABYF  ',
                'Germany',
                1517183998,
                1517184000,
                9.5136,
                48.7467,
                10972.8,
                false,
                157.94,
                108.52,
                null,
                null,
                11582.4,
                '1000',
                false,
                0,
                0
            ]
        ]
    ];

    $client = mockClient([
        new Response(200, [], json_encode($tokenResponse)), // OAuth token request
        new Response(200, [], json_encode($stateResponse))   // API request
    ]);

    // Set OAuth credentials using reflection
    $reflection = new ReflectionClass($client);
    
    $clientIdProperty = $reflection->getProperty('clientId');
    $clientIdProperty->setAccessible(true);
    $clientIdProperty->setValue($client, 'test_client_id');
    
    $clientSecretProperty = $reflection->getProperty('clientSecret');
    $clientSecretProperty->setAccessible(true);
    $clientSecretProperty->setValue($client, 'test_client_secret');

    $response = $client->getOwnStateVectors();

    expect($response)
        ->toBeInstanceOf(StateVectorResponse::class)
        ->and($response->time)->toBe(1517184000)
        ->and($response->states)->toHaveCount(1);
});

it('prefers oauth2 over basic auth when both are configured', function () {
    $tokenResponse = [
        'access_token' => 'oauth_token_preferred',
        'expires_in' => 1800
    ];

    $stateResponse = [
        'time' => 1517184000,
        'states' => []
    ];

    $client = mockClient([
        new Response(200, [], json_encode($tokenResponse)),
        new Response(200, [], json_encode($stateResponse))
    ]);

    $reflection = new ReflectionClass($client);
    
    // Set both OAuth and basic auth credentials
    $clientIdProperty = $reflection->getProperty('clientId');
    $clientIdProperty->setAccessible(true);
    $clientIdProperty->setValue($client, 'test_client_id');
    
    $clientSecretProperty = $reflection->getProperty('clientSecret');
    $clientSecretProperty->setAccessible(true);
    $clientSecretProperty->setValue($client, 'test_client_secret');

    $usernameProperty = $reflection->getProperty('username');
    $usernameProperty->setAccessible(true);
    $usernameProperty->setValue($client, 'test_username');
    
    $passwordProperty = $reflection->getProperty('password');
    $passwordProperty->setAccessible(true);
    $passwordProperty->setValue($client, 'test_password');

    $response = $client->getOwnStateVectors();

    // Should succeed using OAuth2, not basic auth
    expect($response)->toBeInstanceOf(StateVectorResponse::class);
}); 