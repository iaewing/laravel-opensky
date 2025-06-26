<?php

namespace OpenSky\Laravel\DTOs;

use Illuminate\Support\Collection;

class FlightResponse
{
    public function __construct(
        public readonly Collection $flights
    ) {}

    public static function fromArray(array $data): self
    {
        $flights = collect($data)
            ->map(fn(array $flight) => Flight::fromArray($flight));

        return new self(
            flights: $flights
        );
    }
} 