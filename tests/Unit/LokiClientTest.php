<?php

namespace Darkton\Loki\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Darkton\Loki\Exceptions\LokiAuthenticationException;
use Darkton\Loki\Exceptions\LokiConfigurationException;
use Darkton\Loki\Exceptions\LokiPayloadException;
use Darkton\Loki\Exceptions\LokiServerException;
use Darkton\Loki\Services\LokiClient;
use Darkton\Loki\Tests\TestCase;

class LokiClientTest extends TestCase
{
    private function makeClient(array $overrides = []): LokiClient
    {
        return new LokiClient(
            endpoint:         $overrides['endpoint']         ?? 'https://loki.example.com/otlp/v1/logs',
            username:         $overrides['username']         ?? 'testuser',
            apiKey:           $overrides['apiKey']           ?? 'testkey',
            timeout:          $overrides['timeout']          ?? 5,
            serviceName:      $overrides['serviceName']      ?? 'TestApp',
            environment:      $overrides['environment']      ?? 'testing',
            frameworkVersion: $overrides['frameworkVersion'] ?? '12.x',
        );
    }

    // -------------------------------------------------------------------------
    // Constructor validation
    // -------------------------------------------------------------------------

    public function test_throws_when_endpoint_is_empty(): void
    {
        $this->expectException(LokiConfigurationException::class);

        new LokiClient(
            endpoint:  '',
            username:  'user',
            apiKey:    'key',
        );
    }

    public function test_throws_when_endpoint_is_invalid_url(): void
    {
        $this->expectException(LokiConfigurationException::class);

        new LokiClient(
            endpoint:  'not-a-valid-url',
            username:  'user',
            apiKey:    'key',
        );
    }

    public function test_throws_when_timeout_is_zero(): void
    {
        $this->expectException(LokiConfigurationException::class);

        new LokiClient(
            endpoint: 'https://loki.example.com/otlp/v1/logs',
            username: 'user',
            apiKey:   'key',
            timeout:  0,
        );
    }

    public function test_throws_when_username_is_empty(): void
    {
        $this->expectException(LokiAuthenticationException::class);

        new LokiClient(
            endpoint:  'https://loki.example.com/otlp/v1/logs',
            username:  '',
            apiKey:    'key',
        );
    }

    public function test_throws_when_api_key_is_empty(): void
    {
        $this->expectException(LokiAuthenticationException::class);

        new LokiClient(
            endpoint:  'https://loki.example.com/otlp/v1/logs',
            username:  'user',
            apiKey:    '',
        );
    }

    // -------------------------------------------------------------------------
    // send()
    // -------------------------------------------------------------------------

    public function test_send_throws_on_empty_batch(): void
    {
        $client = $this->makeClient();

        $this->expectException(LokiPayloadException::class);

        $client->send([]);
    }

    public function test_send_returns_true_on_success(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $client = $this->makeClient();
        $logs   = [['message' => 'Hello', 'level_name' => 'INFO', 'datetime' => now()->toIso8601String()]];

        $result = $client->send($logs);

        $this->assertTrue($result);
    }

    public function test_send_throws_on_401_response(): void
    {
        Http::fake(['*' => Http::response('Unauthorized', 401)]);

        $client = $this->makeClient();

        $this->expectException(LokiAuthenticationException::class);

        $client->send([['message' => 'test', 'level_name' => 'INFO']]);
    }

    public function test_send_throws_on_500_response(): void
    {
        Http::fake(['*' => Http::response('Internal Server Error', 500)]);

        $client = $this->makeClient();

        $this->expectException(LokiServerException::class);

        $client->send([['message' => 'test', 'level_name' => 'INFO']]);
    }

    public function test_send_uses_basic_auth(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $client = $this->makeClient(['username' => 'myuser', 'apiKey' => 'mykey']);
        $client->send([['message' => 'test', 'level_name' => 'INFO']]);

        Http::assertSent(function (Request $request) {
            return str_contains($request->header('Authorization')[0] ?? '', 'Basic ');
        });
    }

    // -------------------------------------------------------------------------
    // Severity mapping
    // -------------------------------------------------------------------------

    /** @dataProvider severityProvider */
    public function test_severity_number_mapping(string $level, int $expectedNumber): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $client = $this->makeClient();
        $client->send([['message' => 'test', 'level_name' => $level]]);

        Http::assertSent(function (Request $request) use ($expectedNumber) {
            $body    = json_decode($request->body(), true);
            $records = $body['resourceLogs'][0]['scopeLogs'][0]['logRecords'];

            return $records[0]['severityNumber'] === $expectedNumber;
        });
    }

    public static function severityProvider(): array
    {
        return [
            'DEBUG'     => ['DEBUG',     5],
            'INFO'      => ['INFO',      9],
            'NOTICE'    => ['NOTICE',    10],
            'WARNING'   => ['WARNING',   13],
            'ERROR'     => ['ERROR',     17],
            'CRITICAL'  => ['CRITICAL',  19],
            'ALERT'     => ['ALERT',     20],
            'EMERGENCY' => ['EMERGENCY', 21],
        ];
    }
}
