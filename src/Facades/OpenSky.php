<?php

namespace OpenSky\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class OpenSky extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'opensky';
    }
} 