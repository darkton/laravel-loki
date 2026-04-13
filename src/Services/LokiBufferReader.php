<?php

namespace Zie\Loki\Services;

use Zie\Loki\Contracts\LokiBufferInterface;
use Zie\Loki\Exceptions\LokiBufferException;

class LokiBufferReader
{
    public function __construct(private readonly LokiBufferInterface $buffer) {}

    /**
     * Atomically read and remove all entries from the buffer, returning them in batches.
     *
     * This is the preferred method for the loki:sync command, as it guarantees
     * no logs are lost between the read and the clear operation.
     *
     * @return array<int, array<int, array>>
     *
     * @throws LokiBufferException
     */
    public function readAndClearBatches(): array
    {
        return $this->buffer->readAndClearBatches();
    }

    /**
     * Read all entries from the buffer without removing them.
     * Use this when --keep-buffer flag is active.
     *
     * @return array<int, array<int, array>>
     *
     * @throws LokiBufferException
     */
    public function readBatches(): array
    {
        return $this->buffer->readBatches();
    }

    /**
     * @throws LokiBufferException
     */
    public function clearBuffer(): void
    {
        $this->buffer->clearBuffer();
    }

    /**
     * Create a timestamped backup of the buffer.
     *
     * @throws LokiBufferException
     */
    public function backupBuffer(): string
    {
        return $this->buffer->backupBuffer();
    }

    public function count(): int
    {
        return $this->buffer->count();
    }

    public function getBuffer(): LokiBufferInterface
    {
        return $this->buffer;
    }
}
