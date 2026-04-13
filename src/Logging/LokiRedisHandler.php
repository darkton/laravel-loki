<?php

namespace Darkton\Loki\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Darkton\Loki\Contracts\LokiBufferInterface;
use Darkton\Loki\DTOs\LogEntryDTO;

class LokiRedisHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly LokiBufferInterface $buffer,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * Write a Monolog v3 LogRecord to the Redis buffer.
     *
     * Converts the LogRecord to a LogEntryDTO via fromLogRecord(),
     * which correctly extracts level name/value from the Level enum.
     */
    protected function write(LogRecord $record): void
    {
        try {
            $dto = LogEntryDTO::fromLogRecord($record);
            $this->buffer->push($dto);
        } catch (\Exception) {
            // Fail silently — a logging handler must never throw
            // to avoid disrupting the application.
        }
    }

    public function getBuffer(): LokiBufferInterface
    {
        return $this->buffer;
    }
}
