<?php

namespace Zie\Loki\Exceptions;

class LokiServerException extends LokiException
{
    public function getType(): string
    {
        return 'server_error';
    }

    public static function badRequest(int $statusCode, string $responseBody): self
    {
        return new self(
            'Loki rejected the request - bad request',
            [
                'status_code' => $statusCode,
                'response'    => self::truncateResponse($responseBody),
                'suggestion'  => 'Check OTLP payload format or review Loki server logs',
            ]
        );
    }

    public static function serverError(int $statusCode, string $responseBody): self
    {
        return new self(
            'Loki server returned an error',
            [
                'status_code' => $statusCode,
                'response'    => self::truncateResponse($responseBody),
                'suggestion'  => 'Check Loki server health and logs',
            ]
        );
    }

    public static function rateLimited(?int $retryAfter = null): self
    {
        return new self(
            'Rate limit exceeded on Loki server',
            [
                'retry_after_seconds' => $retryAfter,
                'suggestion'          => 'Reduce log volume or increase rate limits on Loki',
            ]
        );
    }

    public static function unexpectedStatus(int $statusCode, string $responseBody): self
    {
        return new self(
            'Loki returned unexpected status code',
            [
                'status_code' => $statusCode,
                'response'    => self::truncateResponse($responseBody),
                'suggestion'  => 'Review Loki documentation for this status code',
            ]
        );
    }

    /**
     * Truncate response body for logging.
     */
    private static function truncateResponse(string $response, int $maxLength = 500): string
    {
        if (strlen($response) <= $maxLength) {
            return $response;
        }

        return substr($response, 0, $maxLength) . '... (truncated)';
    }
}
