<?php

namespace OpenSky\Laravel\DTOs;

use Illuminate\Support\Collection;

class TrackResponse
{
    public function __construct(
        public readonly string $icao24,
        public readonly int $startTime,
        public readonly int $endTime,
        public readonly ?string $callsign,
        public readonly Collection $path
    ) {}

    public static function fromArray(array $data): self
    {
        $path = collect($data['path'] ?? [])
            ->map(fn(array $waypoint) => Waypoint::fromArray($waypoint));

        return new self(
            icao24: $data['icao24'] ?? '',
            startTime: $data['startTime'] ?? 0,
            endTime: $data['endTime'] ?? 0,
            callsign: $data['callsign'] ? trim($data['callsign']) : null,
            path: $path
        );
    }
} 