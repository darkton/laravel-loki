<?php

namespace Darkton\Loki\Providers;

use Illuminate\Support\ServiceProvider;
use Darkton\Loki\Console\Commands\LokiSyncCommand;
use Darkton\Loki\Contracts\LokiBufferInterface;
use Darkton\Loki\Contracts\LokiClientInterface;
use Darkton\Loki\Infrastructure\Redis\LokiRedisBuffer;
use Darkton\Loki\Services\LokiBufferReader;
use Darkton\Loki\Services\LokiClient;

class LokiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/loki.php',
            'loki'
        );

        $this->registerBuffer();
        $this->registerClient();
        $this->registerBufferReader();
    }

    public function boot(): void
    {
        $this->bootPublishes();
        $this->bootCommands();
    }

    private function registerBuffer(): void
    {
        $this->app->bind(LokiBufferInterface::class, function ($app) {
            $cfg = $app['config']['loki'];

            return new LokiRedisBuffer(
                redisKey:       $cfg['redis_key']        ?? 'loki:buffer',
                connection:     $cfg['redis_connection'] ?? 'default',
                batchSize:      (int) ($cfg['batch_size'] ?? 100),
                filterInternal: (bool) ($cfg['debug']    ?? false),
            );
        });
    }

    private function registerClient(): void
    {
        $this->app->bind(LokiClientInterface::class, function ($app) {
            $cfg = $app['config']['loki'];

            return new LokiClient(
                endpoint:         $cfg['otlp_endpoint'] ?? '',
                username:         $cfg['username']      ?? '',
                apiKey:           $cfg['api_key']       ?? '',
                timeout:          (int) ($cfg['timeout'] ?? 5),
                serviceName:      $app['config']['app.name'] ?? 'laravel',
                environment:      $app['config']['app.env']  ?? 'production',
                frameworkVersion: $app->version(),
            );
        });
    }

    private function registerBufferReader(): void
    {
        $this->app->bind(LokiBufferReader::class, function ($app) {
            return new LokiBufferReader(
                $app->make(LokiBufferInterface::class)
            );
        });
    }

    private function bootPublishes(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/loki.php' => config_path('loki.php'),
            ], 'loki-config');
        }
    }

    private function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LokiSyncCommand::class,
            ]);
        }
    }
}
