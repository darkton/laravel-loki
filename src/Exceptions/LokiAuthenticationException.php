<?php

namespace Darkton\Loki\Exceptions;

class LokiAuthenticationException extends LokiException
{
    public function getType(): string
    {
        return 'authentication_error';
    }

    public function isRetryable(): bool
    {
        return false;
    }

    public static function invalidCredentials(int $statusCode): self
    {
        return new self(
            'Loki authentication failed - invalid credentials',
            [
                'status_code' => $statusCode,
                'suggestion'  => 'Verify LOKI_USERNAME and LOKI_API_KEY in your .env file',
            ]
        );
    }

    public static function missingCredentials(): self
    {
        return new self(
            'Loki credentials are missing',
            [
                'suggestion' => 'Set LOKI_USERNAME and LOKI_API_KEY in your .env file',
            ]
        );
    }
}
