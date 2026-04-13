<?php

namespace Darkton\Loki\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Darkton\Loki\Contracts\LokiClientInterface;
use Darkton\Loki\Exceptions\LokiException;

class SendLogsToLokiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  array  $logs  Batch of log entry arrays to send to Loki.
     *
     * Note: Queue connection and name are NOT configured here.
     * The LokiSyncCommand is responsible for applying onConnection()/onQueue()
     * at dispatch time, keeping this job free of config() dependencies.
     */
    public function __construct(private readonly array $logs) {}

    public function handle(LokiClientInterface $client): void
    {
        try {
            $client->send($this->logs);
        } catch (LokiException $e) {
            $this->handleLokiException($e);
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 45];
    }

    public function failed(\Throwable $exception): void
    {
        // Intentionally left without a logger dependency.
        // If the consuming application needs to handle failures,
        // it can listen to the Illuminate\Queue\Events\JobFailed event.
    }

    /**
     * @throws LokiException
     */
    private function handleLokiException(LokiException $exception): void
    {
        if (! $exception->isRetryable()) {
            $this->fail($exception);

            return;
        }

        if ($this->attempts() < $this->tries) {
            throw $exception; // Triggers retry
        }

        $this->fail($exception);
    }
}
