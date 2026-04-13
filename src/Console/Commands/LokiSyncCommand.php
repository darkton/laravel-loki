<?php

namespace Darkton\Loki\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Darkton\Loki\Exceptions\LokiBufferException;
use Darkton\Loki\Exceptions\LokiException;
use Darkton\Loki\Jobs\SendLogsToLokiJob;
use Darkton\Loki\Services\LokiBufferReader;

class LokiSyncCommand extends Command
{
    protected $signature = 'loki:sync
                            {--force        : Override the concurrency lock (use with caution)}
                            {--keep-buffer  : Do not remove entries from the buffer after dispatching}
                            {--backup       : Create a Redis backup of the buffer before processing}';

    protected $description = 'Sync log entries from the Redis buffer to Loki via queued jobs.';

    private const LOCK_KEY = 'loki:sync:lock';

    private const LOCK_TTL = 300;

    public function __construct(private readonly LokiBufferReader $reader)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->acquireLock()) {
            $this->error('Another instance of loki:sync is already running. Use --force to override.');

            return self::FAILURE;
        }

        $connection = config('loki.queue.connection');
        $queue      = config('loki.queue.name', 'default');

        try {
            $total = $this->reader->count();

            if ($total === 0) {
                $this->info('✓ Buffer is empty. Nothing to sync.');

                return self::SUCCESS;
            }

            $this->info("📊 Found {$total} log(s) in buffer.");

            if ($this->option('backup')) {
                $backupKey = $this->reader->backupBuffer();
                $this->info("💾 Buffer backed up to: {$backupKey}");
            }

            if ($this->option('keep-buffer')) {
                // Read-only: entries remain in Redis
                $batches = $this->reader->readBatches();
            } else {
                // Atomic read+remove via Lua script — no entries are lost
                $batches = $this->reader->readAndClearBatches();
            }

            $batchCount = count($batches);

            if ($batchCount === 0) {
                $this->info('✓ No logs to dispatch after filtering.');

                return self::SUCCESS;
            }

            $this->info("📦 Dispatching {$batchCount} batch(es) to queue '{$queue}'...");

            $bar = $this->output->createProgressBar($batchCount);
            $bar->start();

            foreach ($batches as $batch) {
                $job = new SendLogsToLokiJob($batch);

                if ($connection !== null) {
                    $job->onConnection($connection);
                }

                dispatch($job->onQueue($queue));
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("✅ {$batchCount} batch(es) dispatched successfully.");

            if ($batchCount > 0) {
                $this->newLine();
                $this->comment('💡 Monitor jobs with: php artisan horizon');
            }

            return self::SUCCESS;

        } catch (LokiBufferException $e) {
            $this->handleBufferException($e);

            return self::FAILURE;
        } catch (LokiException $e) {
            $this->handleLokiException($e);

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("❌ Unexpected error: {$e->getMessage()}");

            if ($this->output->isVerbose()) {
                $this->newLine();
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return Cache::add(self::LOCK_KEY, true, self::LOCK_TTL);
    }

    private function releaseLock(): void
    {
        Cache::forget(self::LOCK_KEY);
    }

    private function handleBufferException(LokiBufferException $exception): void
    {
        $this->error("❌ Buffer Error: {$exception->getMessage()}");
        $context = $exception->getContext();

        if (isset($context['suggestion'])) {
            $this->newLine();
            $this->comment("💡 Suggestion: {$context['suggestion']}");
        }

        if ($this->output->isVerbose()) {
            $this->newLine();
            $this->table(
                ['Key', 'Value'],
                collect($context)
                    ->map(fn ($value, $key) => [$key, is_array($value) ? json_encode($value) : $value])
                    ->values()
                    ->toArray()
            );
        }
    }

    private function handleLokiException(LokiException $exception): void
    {
        $this->error("❌ Loki Error [{$exception->getType()}]: {$exception->getMessage()}");
        $context = $exception->getContext();

        if (isset($context['suggestion'])) {
            $this->newLine();
            $this->comment("💡 Suggestion: {$context['suggestion']}");
        }

        if ($this->output->isVerbose()) {
            $this->newLine();
            $this->table(
                ['Key', 'Value'],
                collect($context)
                    ->map(fn ($value, $key) => [$key, is_array($value) ? json_encode($value) : $value])
                    ->values()
                    ->toArray()
            );
        }
    }
}
