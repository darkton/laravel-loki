<?php

namespace Darkton\Loki\Tests\Unit;

use Monolog\Level;
use Monolog\LogRecord;
use Darkton\Loki\DTOs\LogEntryDTO;
use Darkton\Loki\Tests\TestCase;

class LogEntryDTOTest extends TestCase
{
    // -------------------------------------------------------------------------
    // fromArray()
    // -------------------------------------------------------------------------

    public function test_from_array_with_all_fields(): void
    {
        $data = [
            'message'    => 'Test message',
            'level_name' => 'ERROR',
            'level'      => 400,
            'channel'    => 'app',
            'datetime'   => '2024-01-01T00:00:00+00:00',
            'context'    => ['user_id' => 1],
            'extra'      => ['env' => 'testing'],
        ];

        $dto = LogEntryDTO::fromArray($data);

        $this->assertSame('Test message', $dto->message);
        $this->assertSame('ERROR', $dto->levelName);
        $this->assertSame(400, $dto->level);
        $this->assertSame('app', $dto->channel);
        $this->assertSame('2024-01-01T00:00:00+00:00', $dto->datetime);
        $this->assertSame(['user_id' => 1], $dto->context);
        $this->assertSame(['env' => 'testing'], $dto->extra);
    }

    public function test_from_array_with_missing_fields_uses_defaults(): void
    {
        $dto = LogEntryDTO::fromArray([]);

        $this->assertSame('', $dto->message);
        $this->assertSame('INFO', $dto->levelName);
        $this->assertSame(0, $dto->level);
        $this->assertSame('', $dto->channel);
        $this->assertIsString($dto->datetime);
        $this->assertSame([], $dto->context);
        $this->assertSame([], $dto->extra);
    }

    public function test_from_array_with_only_message(): void
    {
        $dto = LogEntryDTO::fromArray(['message' => 'Hello world']);

        $this->assertSame('Hello world', $dto->message);
    }

    // -------------------------------------------------------------------------
    // fromLogRecord()
    // -------------------------------------------------------------------------

    public function test_from_log_record_extracts_correct_data(): void
    {
        $datetime = new \DateTimeImmutable('2024-06-01T12:00:00+00:00');

        $record = new LogRecord(
            datetime:  $datetime,
            channel:   'application',
            level:     Level::Warning,
            message:   'Something happened',
            context:   ['key' => 'value'],
            extra:     ['memory' => 1024],
        );

        $dto = LogEntryDTO::fromLogRecord($record);

        $this->assertSame('Something happened', $dto->message);
        $this->assertSame('WARNING', $dto->levelName);
        $this->assertSame(Level::Warning->value, $dto->level);
        $this->assertSame('application', $dto->channel);
        $this->assertSame($datetime->format('c'), $dto->datetime);
        $this->assertSame(['key' => 'value'], $dto->context);
        $this->assertSame(['memory' => 1024], $dto->extra);
    }

    public function test_from_log_record_maps_all_monolog_levels(): void
    {
        // Use a list of pairs — Level enums cannot be used as array keys in PHP
        $levels = [
            [Level::Debug,     'DEBUG'],
            [Level::Info,      'INFO'],
            [Level::Notice,    'NOTICE'],
            [Level::Warning,   'WARNING'],
            [Level::Error,     'ERROR'],
            [Level::Critical,  'CRITICAL'],
            [Level::Alert,     'ALERT'],
            [Level::Emergency, 'EMERGENCY'],
        ];

        foreach ($levels as [$level, $expectedName]) {
            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel:  'test',
                level:    $level,
                message:  'msg',
            );

            $dto = LogEntryDTO::fromLogRecord($record);

            $this->assertSame($expectedName, $dto->levelName, "Failed for level {$expectedName}");
            $this->assertSame($level->value, $dto->level);
        }
    }

    // -------------------------------------------------------------------------
    // toArray()
    // -------------------------------------------------------------------------

    public function test_to_array_produces_expected_structure(): void
    {
        $dto = new LogEntryDTO(
            message:   'My log',
            levelName: 'INFO',
            level:     9,
            channel:   'main',
            datetime:  '2024-01-01T00:00:00+00:00',
            context:   ['foo' => 'bar'],
            extra:     ['env' => 'staging'],
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('level_name', $array);
        $this->assertArrayHasKey('level', $array);
        $this->assertArrayHasKey('channel', $array);
        $this->assertArrayHasKey('datetime', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertArrayHasKey('extra', $array);

        $this->assertSame('My log', $array['message']);
        $this->assertSame('INFO', $array['level_name']);
    }

    public function test_round_trip_from_array_to_array(): void
    {
        $original = [
            'message'    => 'Round trip',
            'level_name' => 'DEBUG',
            'level'      => 100,
            'channel'    => 'queue',
            'datetime'   => '2024-03-15T09:00:00+03:00',
            'context'    => ['trace' => 'abc123'],
            'extra'      => [],
        ];

        $array = LogEntryDTO::fromArray($original)->toArray();

        $this->assertSame($original['message'],    $array['message']);
        $this->assertSame($original['level_name'], $array['level_name']);
        $this->assertSame($original['level'],      $array['level']);
        $this->assertSame($original['channel'],    $array['channel']);
        $this->assertSame($original['datetime'],   $array['datetime']);
        $this->assertSame($original['context'],    $array['context']);
        $this->assertSame($original['extra'],      $array['extra']);
    }
}
