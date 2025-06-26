<?php

namespace OpenSky\Laravel\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenSky\Laravel\Client\OpenSkyClient;
use OpenSky\Laravel\DTOs\StateVectorResponse;
use OpenSky\Laravel\DTOs\FlightResponse;
use OpenSky\Laravel\DTOs\TrackResponse;
use OpenSky\Laravel\Exceptions\OpenSkyException;
use Orchestra\Testbench\TestCase;

class OpenSkyClientTest extends TestCase
{
    private function mockClient(array $responses): OpenSkyClient
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new OpenSkyClient();
        
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $httpClient);

        return $client;
    }

    public function test_get_all_state_vectors(): void
    {
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

        $client = $this->mockClient([
            new Response(200, [], json_encode($responseData))
        ]);

        $response = $client->getAllStateVectors();

        $this->assertInstanceOf(StateVectorResponse::class, $response);
        $this->assertEquals(1517184000, $response->time);
        $this->assertCount(1, $response->states);
        $this->assertEquals('3c4b26', $response->states->first()->icao24);
        $this->assertEquals('D-ABYF', $response->states->first()->callsign);
    }

    public function test_get_flights_in_time_interval(): void
    {
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

        $client = $this->mockClient([
            new Response(200, [], json_encode($responseData))
        ]);

        $response = $client->getFlightsInTimeInterval(1517184000, 1517190000);

        $this->assertInstanceOf(FlightResponse::class, $response);
        $this->assertCount(1, $response->flights);
        $this->assertEquals('3c4b26', $response->flights->first()->icao24);
        $this->assertEquals('DLH441', $response->flights->first()->callsign);
    }

    public function test_get_track_by_aircraft(): void
    {
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

        $client = $this->mockClient([
            new Response(200, [], json_encode($responseData))
        ]);

        $response = $client->getTrackByAircraft('3c4b26', 1517184000);

        $this->assertInstanceOf(TrackResponse::class, $response);
        $this->assertEquals('3c4b26', $response->icao24);
        $this->assertEquals('DLH441', $response->callsign);
        $this->assertCount(2, $response->path);
    }

    public function test_time_interval_validation_flights(): void
    {
        $client = new OpenSkyClient();

        $this->expectException(OpenSkyException::class);
        $this->expectExceptionMessage('Time interval must not be larger than 1 hour');

        $client->getFlightsInTimeInterval(0, 3601);
    }

    public function test_time_interval_validation_aircraft(): void
    {
        $client = new OpenSkyClient();

        $this->expectException(OpenSkyException::class);
        $this->expectExceptionMessage('Time interval must not be larger than 30 days');

        $client->getFlightsByAircraft('3c4b26', 0, 2592001);
    }

    public function test_time_interval_validation_airport(): void
    {
        $client = new OpenSkyClient();

        $this->expectException(OpenSkyException::class);
        $this->expectExceptionMessage('Time interval must not be larger than 7 days');

        $client->getArrivalsByAirport('EDDF', 0, 604801);
    }

    public function test_authentication_required(): void
    {
        $client = new OpenSkyClient();

        $this->expectException(OpenSkyException::class);
        $this->expectExceptionMessage('This endpoint requires authentication. Please provide either username/password or OAuth2 credentials.');

        $client->getOwnStateVectors();
    }

    public function test_oauth2_authentication(): void
    {
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

        $client = $this->mockClient([
            new Response(200, [], json_encode($tokenResponse)), // OAuth token request
            new Response(200, [], json_encode($stateResponse))   // API request
        ]);

        // Set OAuth credentials using reflection since constructor doesn't expose them publicly
        $reflection = new \ReflectionClass($client);
        
        $clientIdProperty = $reflection->getProperty('clientId');
        $clientIdProperty->setAccessible(true);
        $clientIdProperty->setValue($client, 'test_client_id');
        
        $clientSecretProperty = $reflection->getProperty('clientSecret');
        $clientSecretProperty->setAccessible(true);
        $clientSecretProperty->setValue($client, 'test_client_secret');

        $response = $client->getOwnStateVectors();

        $this->assertInstanceOf(StateVectorResponse::class, $response);
        $this->assertEquals(1517184000, $response->time);
        $this->assertCount(1, $response->states);
    }

    public function test_prefers_oauth2_over_basic_auth(): void
    {
        // This test ensures OAuth2 is preferred when both auth methods are configured
        $tokenResponse = [
            'access_token' => 'oauth_token_preferred',
            'expires_in' => 1800
        ];

        $stateResponse = [
            'time' => 1517184000,
            'states' => []
        ];

        $client = $this->mockClient([
            new Response(200, [], json_encode($tokenResponse)),
            new Response(200, [], json_encode($stateResponse))
        ]);

        $reflection = new \ReflectionClass($client);
        
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
        $this->assertInstanceOf(StateVectorResponse::class, $response);
    }

    protected function getPackageProviders($app)
    {
        return [
            \OpenSky\Laravel\OpenSkyServiceProvider::class,
        ];
    }
} 