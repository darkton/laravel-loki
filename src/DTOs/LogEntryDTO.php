<?php

namespace Zie\Loki\DTOs;

use Monolog\LogRecord;

final class LogEntryDTO
{
    public function __construct(
        public readonly string $message,
        public readonly string $levelName,
        public readonly int    $level,
        public readonly string $channel,
        public readonly string $datetime,
        public readonly array  $context = [],
        public readonly array  $extra   = [],
    ) {}

    /**
     * Create a DTO from a raw array (used when reading from Redis buffer).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            message:   $data['message']    ?? '',
            levelName: $data['level_name'] ?? (is_string($data['level'] ?? null) ? $data['level'] : 'INFO'),
            level:     is_int($data['level'] ?? null) ? $data['level'] : 0,
            channel:   $data['channel']    ?? '',
            datetime:  $data['datetime']   ?? $data['time'] ?? (new \DateTimeImmutable())->format('c'),
            context:   is_array($data['context'] ?? null) ? $data['context'] : [],
            extra:     is_array($data['extra'] ?? null)   ? $data['extra']   : [],
        );
    }

    /**
     * Create a DTO from a Monolog v3 LogRecord.
     * Used by LokiRedisHandler::write(LogRecord $record).
     */
    public static function fromLogRecord(LogRecord $record): self
    {
        return new self(
            message:   $record->message,
            levelName: $record->level->getName(),
            level:     $record->level->value,
            channel:   $record->channel,
            datetime:  $record->datetime->format('c'),
            context:   $record->context,
            extra:     $record->extra,
        );
    }

    /**
     * Serialize the DTO to an array (stored in Redis as JSON).
     */
    public function toArray(): array
    {
        return [
            'message'    => $this->message,
            'level_name' => $this->levelName,
            'level'      => $this->level,
            'channel'    => $this->channel,
            'datetime'   => $this->datetime,
            'context'    => $this->context,
            'extra'      => $this->extra,
        ];
    }
}
