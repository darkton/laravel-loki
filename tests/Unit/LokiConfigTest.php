<?php

namespace Darkton\Loki\Tests\Unit;

use Darkton\Loki\Tests\TestCase;

class LokiConfigTest extends TestCase
{
    public function test_config_has_all_required_keys(): void
    {
        $config = config('loki');

        $this->assertArrayHasKey('otlp_endpoint',    $config);
        $this->assertArrayHasKey('username',          $config);
        $this->assertArrayHasKey('api_key',           $config);
        $this->assertArrayHasKey('timeout',           $config);
        $this->assertArrayHasKey('buffer_size',       $config);
        $this->assertArrayHasKey('batch_size',        $config);
        $this->assertArrayHasKey('debug',             $config);
        $this->assertArrayHasKey('redis_connection',  $config);
        $this->assertArrayHasKey('redis_key',         $config);
        $this->assertArrayHasKey('queue',             $config);
    }

    public function test_queue_config_has_connection_and_name(): void
    {
        $queue = config('loki.queue');

        $this->assertIsArray($queue);
        $this->assertArrayHasKey('connection', $queue);
        $this->assertArrayHasKey('name',       $queue);
    }

    public function test_default_values(): void
    {
        $this->assertSame(5,          config('loki.timeout'));
        $this->assertSame(100,        config('loki.buffer_size'));
        $this->assertSame(100,        config('loki.batch_size'));
        $this->assertSame(false,      config('loki.debug'));
        $this->assertSame('default',  config('loki.redis_connection'));
        $this->assertSame('loki:buffer', config('loki.redis_key'));
    }

    public function test_queue_connection_defaults_to_null(): void
    {
        // null means "use the Laravel default connection"
        $this->assertNull(config('loki.queue.connection'));
    }

    public function test_queue_name_defaults_to_default(): void
    {
        $this->assertSame('default', config('loki.queue.name'));
    }

    public function test_timeout_is_integer(): void
    {
        $this->assertIsInt(config('loki.timeout'));
    }

    public function test_batch_size_is_integer(): void
    {
        $this->assertIsInt(config('loki.batch_size'));
    }

    public function test_debug_is_boolean(): void
    {
        $this->assertIsBool(config('loki.debug'));
    }
}
