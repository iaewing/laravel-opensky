<?php

namespace OpenSky\Laravel\Enums;

enum AircraftCategory: int
{
    case NO_INFORMATION = 0;
    case NO_ADS_B_EMITTER_CATEGORY = 1;
    case LIGHT = 2; // < 15500 lbs
    case SMALL = 3; // 15500 to 75000 lbs
    case LARGE = 4; // 75000 to 300000 lbs
    case HIGH_VORTEX_LARGE = 5; // aircraft such as B-757
    case HEAVY = 6; // > 300000 lbs
    case HIGH_PERFORMANCE = 7; // > 5g acceleration and 400 kts
    case ROTORCRAFT = 8;
    case GLIDER_SAILPLANE = 9;
    case LIGHTER_THAN_AIR = 10;
    case PARACHUTIST_SKYDIVER = 11;
    case ULTRALIGHT = 12; // hang-glider/paraglider
    case RESERVED = 13;
    case UNMANNED_AERIAL_VEHICLE = 14;
    case SPACE_TRANS_ATMOSPHERIC = 15;
    case SURFACE_EMERGENCY_VEHICLE = 16;
    case SURFACE_SERVICE_VEHICLE = 17;
    case POINT_OBSTACLE = 18; // includes tethered balloons
    case CLUSTER_OBSTACLE = 19;
    case LINE_OBSTACLE = 20;

    public function description(): string
    {
        return match ($this) {
            self::NO_INFORMATION => 'No information at all',
            self::NO_ADS_B_EMITTER_CATEGORY => 'No ADS-B Emitter Category Information',
            self::LIGHT => 'Light (< 15500 lbs)',
            self::SMALL => 'Small (15500 to 75000 lbs)',
            self::LARGE => 'Large (75000 to 300000 lbs)',
            self::HIGH_VORTEX_LARGE => 'High Vortex Large (aircraft such as B-757)',
            self::HEAVY => 'Heavy (> 300000 lbs)',
            self::HIGH_PERFORMANCE => 'High Performance (> 5g acceleration and 400 kts)',
            self::ROTORCRAFT => 'Rotorcraft',
            self::GLIDER_SAILPLANE => 'Glider / sailplane',
            self::LIGHTER_THAN_AIR => 'Lighter-than-air',
            self::PARACHUTIST_SKYDIVER => 'Parachutist / Skydiver',
            self::ULTRALIGHT => 'Ultralight / hang-glider / paraglider',
            self::RESERVED => 'Reserved',
            self::UNMANNED_AERIAL_VEHICLE => 'Unmanned Aerial Vehicle',
            self::SPACE_TRANS_ATMOSPHERIC => 'Space / Trans-atmospheric vehicle',
            self::SURFACE_EMERGENCY_VEHICLE => 'Surface Vehicle – Emergency Vehicle',
            self::SURFACE_SERVICE_VEHICLE => 'Surface Vehicle – Service Vehicle',
            self::POINT_OBSTACLE => 'Point Obstacle (includes tethered balloons)',
            self::CLUSTER_OBSTACLE => 'Cluster Obstacle',
            self::LINE_OBSTACLE => 'Line Obstacle',
        };
    }
} 