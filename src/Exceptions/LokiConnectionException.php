<?php

namespace Zie\Loki\Exceptions;

class LokiConnectionException extends LokiException
{
    public function getType(): string
    {
        return 'connection_error';
    }

    public static function timeout(int $timeout, string $endpoint): self
    {
        return new self(
            "Connection to Loki timed out after {$timeout} seconds",
            [
                'timeout'    => $timeout,
                'endpoint'   => $endpoint,
                'suggestion' => 'Check network connectivity or increase LOKI_TIMEOUT',
            ]
        );
    }

    public static function unreachable(string $endpoint, string $reason): self
    {
        return new self(
            'Loki endpoint is unreachable',
            [
                'endpoint'   => $endpoint,
                'reason'     => $reason,
                'suggestion' => 'Verify LOKI_OTLP_ENDPOINT configuration and network access',
            ]
        );
    }

    public static function dnsResolutionFailed(string $endpoint): self
    {
        return new self(
            'Failed to resolve Loki endpoint DNS',
            [
                'endpoint'   => $endpoint,
                'suggestion' => 'Check if the hostname is correct and DNS is accessible',
            ]
        );
    }
}
