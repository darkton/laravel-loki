<?php

namespace Darkton\Loki\Exceptions;

class LokiBufferException extends LokiException
{
    public function getType(): string
    {
        return 'buffer_error';
    }

    public static function readFailed(string $key, string $reason): self
    {
        return new self(
            'Failed to read from Loki Redis buffer',
            [
                'redis_key'  => $key,
                'reason'     => $reason,
                'suggestion' => 'Check Redis connection and configuration',
            ]
        );
    }

    public static function writeFailed(string $key, string $reason): self
    {
        return new self(
            'Failed to write to Loki Redis buffer',
            [
                'redis_key'  => $key,
                'reason'     => $reason,
                'suggestion' => 'Check Redis connection and configuration',
            ]
        );
    }

    public static function invalidJson(string $line, int $lineNumber): self
    {
        return new self(
            'Invalid JSON found in Loki buffer',
            [
                'line_number'  => $lineNumber,
                'line_preview' => substr($line, 0, 100),
                'suggestion'   => 'Buffer may be corrupted. Consider clearing it with loki:sync --force',
            ]
        );
    }

    public static function corruptedBuffer(string $key): self
    {
        return new self(
            'Loki Redis buffer is corrupted',
            [
                'redis_key'  => $key,
                'suggestion' => 'Clear the Redis buffer with php artisan loki:sync --force',
            ]
        );
    }
}
