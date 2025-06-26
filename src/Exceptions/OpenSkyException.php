<?php

namespace OpenSky\Laravel\Exceptions;

use Exception;

class OpenSkyException extends Exception
{
    public static function authenticationRequired(): self
    {
        return new self('This endpoint requires authentication. Please provide either username/password or OAuth2 credentials.');
    }

    public static function invalidTimeInterval(string $maxInterval): self
    {
        return new self("Time interval must not be larger than {$maxInterval}");
    }

    public static function apiRequestFailed(string $message, int $code = 0, ?Exception $previous = null): self
    {
        return new self("OpenSky API request failed: {$message}", $code, $previous);
    }

    public static function oauthTokenFailed(string $message): self
    {
        return new self("Failed to obtain OAuth2 token: {$message}");
    }

    public static function rateLimitExceeded(int $retryAfter = null): self
    {
        $message = 'OpenSky API rate limit exceeded.';
        if ($retryAfter) {
            $message .= " Retry after {$retryAfter} seconds.";
        }
        return new self($message);
    }
} 