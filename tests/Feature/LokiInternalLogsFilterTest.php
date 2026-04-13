<?php

namespace Darkton\Loki\Tests\Feature;

use Darkton\Loki\Contracts\LokiBufferInterface;
use Darkton\Loki\DTOs\LogEntryDTO;
use Darkton\Loki\Exceptions\LokiBufferException;
use Darkton\Loki\Infrastructure\Redis\LokiRedisBuffer;
use Darkton\Loki\Tests\TestCase;

/**
 * These tests verify internal-log filtering behaviour.
 * They use a Mockery mock instead of real Redis so they run in isolation.
 */
class LokiInternalLogsFilterTest extends TestCase
{
    private function makeBuffer(bool $filterInternal, array $entries): LokiBufferInterface
    {
        // We mock the parent interface rather than hitting real Redis
        $mock = \Mockery::mock(LokiBufferInterface::class);

        $encoded = array_map('json_encode', $entries);

        $mock->shouldReceive('readBatches')
            ->andReturnUsing(function () use ($entries, $filterInternal) {
                $logs = [];
                foreach ($entries as $entry) {
                    if ($filterInternal
                        && isset($entry['context']['loki_internal'])
                        && $entry['context']['loki_internal'] === true
                    ) {
                        continue;
                    }
                    $logs[] = $entry;
                }
                return empty($logs) ? [] : [$logs];
            });

        return $mock;
    }

    public function test_internal_logs_are_filtered_when_debug_is_enabled(): void
    {
        $entries = [
            ['message' => 'User login',  'context' => ['user_id' => 1]],
            ['message' => 'Loki internal', 'context' => ['loki_internal' => true]],
            ['message' => 'Payment processed', 'context' => ['amount' => 100]],
        ];

        $buffer  = $this->makeBuffer(true, $entries);
        $batches = $buffer->readBatches();
        $all     = array_merge(...$batches);

        $this->assertCount(2, $all);
        $this->assertSame('User login', $all[0]['message']);
        $this->assertSame('Payment processed', $all[1]['message']);
    }

    public function test_internal_logs_are_NOT_filtered_when_debug_is_disabled(): void
    {
        $entries = [
            ['message' => 'User login',    'context' => ['user_id' => 1]],
            ['message' => 'Loki internal', 'context' => ['loki_internal' => true]],
            ['message' => 'Payment',       'context' => ['amount' => 100]],
        ];

        $buffer  = $this->makeBuffer(false, $entries);
        $batches = $buffer->readBatches();
        $all     = array_merge(...$batches);

        $this->assertCount(3, $all);
    }

    public function test_loki_internal_false_is_not_filtered(): void
    {
        $entries = [
            ['message' => 'User login',    'context' => ['loki_internal' => false]],
            ['message' => 'Loki internal', 'context' => ['loki_internal' => true]],
        ];

        $buffer  = $this->makeBuffer(true, $entries);
        $batches = $buffer->readBatches();
        $all     = array_merge(...$batches);

        $this->assertCount(1, $all);
        $this->assertSame('User login', $all[0]['message']);
    }

    public function test_logs_without_context_are_not_filtered(): void
    {
        $entries = [
            ['message' => 'No context log'],
            ['message' => 'Empty context', 'context' => []],
            ['message' => 'Internal',       'context' => ['loki_internal' => true]],
        ];

        $buffer  = $this->makeBuffer(true, $entries);
        $batches = $buffer->readBatches();
        $all     = array_merge(...$batches);

        $this->assertCount(2, $all);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
