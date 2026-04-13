<?php

namespace Darkton\Loki\Contracts;

use Darkton\Loki\DTOs\LogEntryDTO;
use Darkton\Loki\Exceptions\LokiBufferException;

interface LokiBufferInterface
{
    /**
     * Add a log entry to the buffer.
     *
     * @throws LokiBufferException
     */
    public function push(LogEntryDTO $entry): void;

    /**
     * Atomically read and remove all entries from the buffer, returning them in batches.
     * Uses a Lua script to ensure no logs are lost between read and clear operations.
     *
     * @return array<int, array<int, array>> Batches of log entry arrays.
     *
     * @throws LokiBufferException
     */
    public function readAndClearBatches(): array;

    /**
     * Read all entries from the buffer without removing them (read-only, no side effects).
     *
     * @return array<int, array<int, array>> Batches of log entry arrays.
     *
     * @throws LokiBufferException
     */
    public function readBatches(): array;

    /**
     * Clear all entries from the buffer.
     *
     * @throws LokiBufferException
     */
    public function clearBuffer(): void;

    /**
     * Create a timestamped backup of the buffer in Redis.
     *
     * @return string The backup key name.
     *
     * @throws LokiBufferException
     */
    public function backupBuffer(): string;

    /**
     * Count the number of entries currently in the buffer.
     */
    public function count(): int;
}
