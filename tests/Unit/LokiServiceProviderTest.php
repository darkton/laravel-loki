<?php

namespace Zie\Loki\Tests\Unit;

use Zie\Loki\Contracts\LokiBufferInterface;
use Zie\Loki\Contracts\LokiClientInterface;
use Zie\Loki\Console\Commands\LokiSyncCommand;
use Zie\Loki\Infrastructure\Redis\LokiRedisBuffer;
use Zie\Loki\Services\LokiBufferReader;
use Zie\Loki\Services\LokiClient;
use Zie\Loki\Tests\TestCase;

class LokiServiceProviderTest extends TestCase
{
    public function test_loki_client_interface_is_bound(): void
    {
        $this->app['config']->set('loki.otlp_endpoint', 'https://loki.example.com/otlp/v1/logs');
        $this->app['config']->set('loki.username', 'user');
        $this->app['config']->set('loki.api_key', 'secret');

        $this->assertTrue($this->app->bound(LokiClientInterface::class));
    }

    public function test_loki_buffer_interface_is_bound(): void
    {
        $this->assertTrue($this->app->bound(LokiBufferInterface::class));
    }

    public function test_loki_buffer_reader_is_bound(): void
    {
        $this->assertTrue($this->app->bound(LokiBufferReader::class));
    }

    public function test_loki_buffer_resolves_to_redis_buffer(): void
    {
        $buffer = $this->app->make(LokiBufferInterface::class);

        $this->assertInstanceOf(LokiRedisBuffer::class, $buffer);
    }

    public function test_loki_client_resolves_to_loki_client_implementation(): void
    {
        $this->app['config']->set('loki.otlp_endpoint', 'https://loki.example.com/otlp/v1/logs');
        $this->app['config']->set('loki.username', 'user');
        $this->app['config']->set('loki.api_key', 'secret');

        $client = $this->app->make(LokiClientInterface::class);

        $this->assertInstanceOf(LokiClient::class, $client);
    }

    public function test_loki_config_is_loaded(): void
    {
        $this->assertNotNull(config('loki'));
        $this->assertIsArray(config('loki'));
    }

    public function test_loki_config_publish_tag_is_registered(): void
    {
        $publishes = \Illuminate\Support\ServiceProvider::pathsToPublish(
            null,
            'loki-config'
        );

        $this->assertNotEmpty($publishes);
    }

    public function test_loki_sync_command_is_registered(): void
    {
        $commands = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all();

        $this->assertArrayHasKey('loki:sync', $commands);
    }
}
