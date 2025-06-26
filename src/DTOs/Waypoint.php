<?php

namespace OpenSky\Laravel\DTOs;

class Waypoint
{
    public function __construct(
        public readonly int $time,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?float $baroAltitude,
        public readonly ?float $trueTrack,
        public readonly bool $onGround
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            time: $data[0] ?? 0,
            latitude: $data[1],
            longitude: $data[2],
            baroAltitude: $data[3],
            trueTrack: $data[4],
            onGround: $data[5] ?? false
        );
    }
} 