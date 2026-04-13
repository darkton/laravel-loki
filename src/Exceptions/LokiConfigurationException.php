<?php

namespace Darkton\Loki\Exceptions;

class LokiConfigurationException extends LokiException
{
    public function getType(): string
    {
        return 'configuration_error';
    }

    public function isRetryable(): bool
    {
        return false;
    }

    public static function missingEndpoint(): self
    {
        return new self(
            'Loki OTLP endpoint is not configured',
            [
                'suggestion' => 'Set LOKI_OTLP_ENDPOINT in your .env file',
                'config_key' => 'loki.otlp_endpoint',
            ]
        );
    }

    public static function invalidEndpoint(string $endpoint): self
    {
        return new self(
            'Loki OTLP endpoint has invalid format',
            [
                'endpoint'   => $endpoint,
                'suggestion' => 'Ensure LOKI_OTLP_ENDPOINT is a valid URL (https://...)',
            ]
        );
    }

    public static function invalidTimeout(mixed $timeout): self
    {
        return new self(
            'Loki timeout configuration is invalid',
            [
                'timeout'    => $timeout,
                'suggestion' => 'Set LOKI_TIMEOUT to a positive integer (default: 5)',
            ]
        );
    }

    public static function invalidBatchSize(mixed $batchSize): self
    {
        return new self(
            'Loki batch size configuration is invalid',
            [
                'batch_size' => $batchSize,
                'suggestion' => 'Set LOKI_BATCH_SIZE to a positive integer (default: 100)',
            ]
        );
    }
}
