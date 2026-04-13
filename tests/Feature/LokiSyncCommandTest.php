<?php

namespace Zie\Loki\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Mockery;
use Zie\Loki\Contracts\LokiBufferInterface;
use Zie\Loki\DTOs\LogEntryDTO;
use Zie\Loki\Jobs\SendLogsToLokiJob;
use Zie\Loki\Services\LokiBufferReader;
use Zie\Loki\Tests\TestCase;

class LokiSyncCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    // -------------------------------------------------------------------------
    // Empty buffer
    // -------------------------------------------------------------------------

    public function test_exits_successfully_when_buffer_is_empty(): void
    {
        $buffer = Mockery::mock(LokiBufferInterface::class);
        $buffer->shouldReceive('count')->once()->andReturn(0);

        $this->app->instance(LokiBufferInterface::class, $buffer);
        $this->app->bind(LokiBufferReader::class, fn ($app) => new LokiBufferReader($buffer));

        $this->artisan('loki:sync')
            ->expectsOutputToContain('Buffer is empty')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------------------
    // Normal dispatch
    // -------------------------------------------------------------------------

    public function test_dispatches_one_job_per_batch(): void
    {
        $batches = [
            [['message' => 'log 1'], ['message' => 'log 2']],
            [['message' => 'log 3']],
        ];

        $buffer = Mockery::mock(LokiBufferInterface::class);
        $buffer->shouldReceive('count')->once()->andReturn(3);
        $buffer->shouldReceive('readAndClearBatches')->once()->andReturn($batches);

        $this->app->instance(LokiBufferInterface::class, $buffer);
        $this->app->bind(LokiBufferReader::class, fn ($app) => new LokiBufferReader($buffer));

        $this->artisan('loki:sync')
            ->assertExitCode(0);

        Queue::assertPushed(SendLogsToLokiJob::class, 2);
    }

    // -------------------------------------------------------------------------
    // --keep-buffer
    // -------------------------------------------------------------------------

    public function test_keep_buffer_uses_read_only_method(): void
    {
        $batches = [[['message' => 'log 1']]];

        $buffer = Mockery::mock(LokiBufferInterface::class);
        $buffer->shouldReceive('count')->once()->andReturn(1);
        // readAndClearBatches must NOT be called
        $buffer->shouldNotReceive('readAndClearBatches');
        // readBatches IS called
        $buffer->shouldReceive('readBatches')->once()->andReturn($batches);

        $this->app->instance(LokiBufferInterface::class, $buffer);
        $this->app->bind(LokiBufferReader::class, fn ($app) => new LokiBufferReader($buffer));

        $this->artisan('loki:sync', ['--keep-buffer' => true])
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------------------
    // --backup
    // -------------------------------------------------------------------------

    public function test_backup_flag_calls_backup_buffer(): void
    {
        $batches = [[['message' => 'log 1']]];

        $buffer = Mockery::mock(LokiBufferInterface::class);
        $buffer->shouldReceive('count')->once()->andReturn(1);
        $buffer->shouldReceive('backupBuffer')->once()->andReturn('loki:buffer:backup:12345');
        $buffer->shouldReceive('readAndClearBatches')->once()->andReturn($batches);

        $this->app->instance(LokiBufferInterface::class, $buffer);
        $this->app->bind(LokiBufferReader::class, fn ($app) => new LokiBufferReader($buffer));

        $this->artisan('loki:sync', ['--backup' => true])
            ->expectsOutputToContain('backed up')
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------------------
    // Queue configuration
    // -------------------------------------------------------------------------

    public function test_jobs_are_dispatched_to_configured_queue(): void
    {
        $this->app['config']->set('loki.queue.name', 'loki-queue');
        $this->app['config']->set('loki.queue.connection', null);

        $batches = [[['message' => 'log 1']]];

        $buffer = Mockery::mock(LokiBufferInterface::class);
        $buffer->shouldReceive('count')->once()->andReturn(1);
        $buffer->shouldReceive('readAndClearBatches')->once()->andReturn($batches);

        $this->app->instance(LokiBufferInterface::class, $buffer);
        $this->app->bind(LokiBufferReader::class, fn ($app) => new LokiBufferReader($buffer));

        $this->artisan('loki:sync')->assertExitCode(0);

        Queue::assertPushedOn('loki-queue', SendLogsToLokiJob::class);
    }

    // -------------------------------------------------------------------------
    // --force (lock)
    // -------------------------------------------------------------------------

    public function test_force_flag_bypasses_lock(): void
    {
        // Simulate lock already acquired
        \Illuminate\Support\Facades\Cache::put('loki:sync:lock', true, 300);

        $batches = [[['message' => 'log 1']]];

        $buffer = Mockery::mock(LokiBufferInterface::class);
        $buffer->shouldReceive('count')->once()->andReturn(1);
        $buffer->shouldReceive('readAndClearBatches')->once()->andReturn($batches);

        $this->app->instance(LokiBufferInterface::class, $buffer);
        $this->app->bind(LokiBufferReader::class, fn ($app) => new LokiBufferReader($buffer));

        $this->artisan('loki:sync', ['--force' => true])
            ->assertExitCode(0);
    }

    public function test_fails_when_lock_is_held_without_force(): void
    {
        \Illuminate\Support\Facades\Cache::put('loki:sync:lock', true, 300);

        $this->artisan('loki:sync')
            ->expectsOutputToContain('already running')
            ->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
