<?php

namespace OpenSky\Laravel\DTOs;

class Flight
{
    public function __construct(
        public readonly string $icao24,
        public readonly ?int $firstSeen,
        public readonly string $estDepartureAirport,
        public readonly ?int $lastSeen,
        public readonly string $estArrivalAirport,
        public readonly ?string $callsign,
        public readonly ?int $estDepartureAirportHorizDistance,
        public readonly ?int $estDepartureAirportVertDistance,
        public readonly ?int $estArrivalAirportHorizDistance,
        public readonly ?int $estArrivalAirportVertDistance,
        public readonly ?int $departureAirportCandidatesCount,
        public readonly ?int $arrivalAirportCandidatesCount
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            icao24: $data['icao24'] ?? '',
            firstSeen: $data['firstSeen'],
            estDepartureAirport: $data['estDepartureAirport'] ?? '',
            lastSeen: $data['lastSeen'],
            estArrivalAirport: $data['estArrivalAirport'] ?? '',
            callsign: $data['callsign'] ? trim($data['callsign']) : null,
            estDepartureAirportHorizDistance: $data['estDepartureAirportHorizDistance'],
            estDepartureAirportVertDistance: $data['estDepartureAirportVertDistance'],
            estArrivalAirportHorizDistance: $data['estArrivalAirportHorizDistance'],
            estArrivalAirportVertDistance: $data['estArrivalAirportVertDistance'],
            departureAirportCandidatesCount: $data['departureAirportCandidatesCount'],
            arrivalAirportCandidatesCount: $data['arrivalAirportCandidatesCount']
        );
    }
} 