<?php

namespace Zie\Loki\Exceptions;

class LokiPayloadException extends LokiException
{
    public function getType(): string
    {
        return 'payload_error';
    }

    public function isRetryable(): bool
    {
        return false;
    }

    public static function invalidFormat(string $reason, array $sample = []): self
    {
        return new self(
            'Loki payload has invalid format',
            [
                'reason'     => $reason,
                'sample'     => ! empty($sample) ? json_encode($sample) : 'N/A',
                'suggestion' => 'Check log entry structure before sending to Loki',
            ]
        );
    }

    public static function tooLarge(int $size, int $maxSize): self
    {
        return new self(
            'Loki payload exceeds maximum size',
            [
                'size_bytes'     => $size,
                'max_size_bytes' => $maxSize,
                'suggestion'     => 'Reduce LOKI_BATCH_SIZE in config/loki.php',
            ]
        );
    }

    public static function emptyBatch(): self
    {
        return new self(
            'Attempted to send empty batch to Loki',
            [
                'suggestion' => 'Ensure logs are properly formatted before batching',
            ]
        );
    }
}
