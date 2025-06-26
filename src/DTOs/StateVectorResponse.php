<?php

namespace OpenSky\Laravel\DTOs;

use Illuminate\Support\Collection;

class StateVectorResponse
{
    public function __construct(
        public readonly int $time,
        public readonly Collection $states
    ) {}

    public static function fromArray(array $data): self
    {
        $states = collect($data['states'] ?? [])
            ->map(fn(array $state) => StateVector::fromArray($state));

        return new self(
            time: $data['time'] ?? 0,
            states: $states
        );
    }
} 