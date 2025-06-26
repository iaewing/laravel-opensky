<?php

namespace OpenSky\Laravel\DTOs;

class StateVector
{
    public function __construct(
        public readonly string $icao24,
        public readonly ?string $callsign,
        public readonly string $originCountry,
        public readonly ?int $timePosition,
        public readonly int $lastContact,
        public readonly ?float $longitude,
        public readonly ?float $latitude,
        public readonly ?float $baroAltitude,
        public readonly bool $onGround,
        public readonly ?float $velocity,
        public readonly ?float $trueTrack,
        public readonly ?float $verticalRate,
        public readonly ?array $sensors,
        public readonly ?float $geoAltitude,
        public readonly ?string $squawk,
        public readonly bool $spi,
        public readonly int $positionSource,
        public readonly ?int $category = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            icao24: $data[0] ?? '',
            callsign: $data[1] ? trim($data[1]) : null,
            originCountry: $data[2] ?? '',
            timePosition: $data[3],
            lastContact: $data[4] ?? 0,
            longitude: $data[5],
            latitude: $data[6],
            baroAltitude: $data[7],
            onGround: $data[8] ?? false,
            velocity: $data[9],
            trueTrack: $data[10],
            verticalRate: $data[11],
            sensors: $data[12],
            geoAltitude: $data[13],
            squawk: $data[14],
            spi: $data[15] ?? false,
            positionSource: $data[16] ?? 0,
            category: $data[17] ?? null
        );
    }
} 