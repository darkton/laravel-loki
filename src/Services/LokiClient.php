<?php

namespace Darkton\Loki\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Darkton\Loki\Contracts\LokiClientInterface;
use Darkton\Loki\Exceptions\LokiAuthenticationException;
use Darkton\Loki\Exceptions\LokiConfigurationException;
use Darkton\Loki\Exceptions\LokiConnectionException;
use Darkton\Loki\Exceptions\LokiPayloadException;
use Darkton\Loki\Exceptions\LokiServerException;

class LokiClient implements LokiClientInterface
{
    /**
     * @throws LokiConfigurationException
     * @throws LokiAuthenticationException
     */
    public function __construct(
        private readonly string $endpoint,
        private readonly string $username,
        private readonly string $apiKey,
        private readonly int    $timeout          = 5,
        private readonly string $serviceName      = 'laravel',
        private readonly string $environment      = 'production',
        private readonly string $frameworkVersion = '12.x',
    ) {
        $this->validateConfiguration();
    }

    /**
     * @throws LokiPayloadException
     * @throws LokiConnectionException
     * @throws LokiAuthenticationException
     * @throws LokiServerException
     * @throws ConnectionException
     */
    public function send(array $logs): bool
    {
        if (empty($logs)) {
            throw LokiPayloadException::emptyBatch();
        }

        $payload     = $this->buildOtlpPayload($logs);
        $payloadSize = strlen(json_encode($payload));
        $maxSize     = 10 * 1024 * 1024; // 10 MB

        if ($payloadSize > $maxSize) {
            throw LokiPayloadException::tooLarge($payloadSize, $maxSize);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->apiKey)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint, $payload);

            return $this->handleResponse($response, count($logs));

        } catch (ConnectionException $e) {
            throw $this->handleConnectionException($e);
        } catch (RequestException $e) {
            throw $this->handleRequestException($e);
        }
    }

    /**
     * @throws LokiConfigurationException
     * @throws LokiAuthenticationException
     */
    private function validateConfiguration(): void
    {
        if (empty($this->endpoint)) {
            throw LokiConfigurationException::missingEndpoint();
        }

        if (! filter_var($this->endpoint, FILTER_VALIDATE_URL)) {
            throw LokiConfigurationException::invalidEndpoint($this->endpoint);
        }

        if ($this->timeout <= 0) {
            throw LokiConfigurationException::invalidTimeout($this->timeout);
        }

        if (empty($this->username) || empty($this->apiKey)) {
            throw LokiAuthenticationException::missingCredentials();
        }
    }

    /**
     * @throws LokiAuthenticationException
     * @throws LokiServerException
     */
    private function handleResponse(Response $response, int $logsCount): bool
    {
        $statusCode   = $response->status();
        $responseBody = $response->body();

        if ($response->successful()) {
            return true;
        }

        if ($statusCode === 401 || $statusCode === 403) {
            throw LokiAuthenticationException::invalidCredentials($statusCode);
        }

        if ($statusCode === 400) {
            throw LokiServerException::badRequest($statusCode, $responseBody);
        }

        if ($statusCode === 429) {
            $retryAfter = $response->header('Retry-After');
            throw LokiServerException::rateLimited($retryAfter !== '' ? (int) $retryAfter : null);
        }

        if ($statusCode >= 500) {
            throw LokiServerException::serverError($statusCode, $responseBody);
        }

        throw LokiServerException::unexpectedStatus($statusCode, $responseBody);
    }

    private function handleConnectionException(ConnectionException $e): LokiConnectionException
    {
        $message = $e->getMessage();

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return LokiConnectionException::timeout($this->timeout, $this->endpoint);
        }

        if (str_contains($message, 'resolve') || str_contains($message, 'DNS')) {
            return LokiConnectionException::dnsResolutionFailed($this->endpoint);
        }

        return LokiConnectionException::unreachable($this->endpoint, $message);
    }

    private function handleRequestException(RequestException $e): LokiServerException
    {
        $response = $e->response;

        if ($response) {
            return LokiServerException::serverError($response->status(), $response->body());
        }

        return LokiServerException::unexpectedStatus(0, $e->getMessage());
    }

    private function buildOtlpPayload(array $logs): array
    {
        $logRecords = array_map(fn ($log) => $this->transformToOtlpLogRecord($log), $logs);

        return [
            'resourceLogs' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name',          'value' => ['stringValue' => $this->serviceName]],
                            ['key' => 'deployment.environment','value' => ['stringValue' => $this->environment]],
                            ['key' => 'application',           'value' => ['stringValue' => $this->serviceName]],
                        ],
                    ],
                    'scopeLogs' => [
                        [
                            'scope' => [
                                'name'    => 'laravel-monolog',
                                'version' => $this->frameworkVersion,
                            ],
                            'logRecords' => $logRecords,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function transformToOtlpLogRecord(array $log): array
    {
        $timestamp = $this->parseTimestamp(
            $log['datetime'] ?? $log['time'] ?? (new \DateTimeImmutable())->format('c')
        );

        return [
            'timeUnixNano'   => (string) $timestamp,
            'severityText'   => $this->normalizeSeverity($log['level_name'] ?? $log['level'] ?? 'INFO'),
            'severityNumber' => $this->getSeverityNumber($log['level_name'] ?? $log['level'] ?? 'INFO'),
            'body'           => ['stringValue' => $log['message'] ?? ''],
            'attributes'     => $this->buildAttributes($log),
        ];
    }

    private function parseTimestamp(string $datetime): int
    {
        try {
            $dt = new \DateTime($datetime);

            return (int) ($dt->getTimestamp() * 1_000_000_000);
        } catch (\Exception) {
            return (int) ((new \DateTime())->getTimestamp() * 1_000_000_000);
        }
    }

    private function normalizeSeverity(string $level): string
    {
        return strtoupper($level);
    }

    private function getSeverityNumber(string $level): int
    {
        return match (strtoupper($level)) {
            'DEBUG'     => 5,
            'INFO'      => 9,
            'NOTICE'    => 10,
            'WARNING'   => 13,
            'ERROR'     => 17,
            'CRITICAL'  => 19,
            'ALERT'     => 20,
            'EMERGENCY' => 21,
            default     => 9,
        };
    }

    private function buildAttributes(array $log): array
    {
        $attributes = [];

        if (isset($log['context']) && is_array($log['context'])) {
            foreach ($log['context'] as $key => $value) {
                $attributes[] = [
                    'key'   => (string) $key,
                    'value' => $this->convertValue($value),
                ];
            }
        }

        if (isset($log['extra']) && is_array($log['extra'])) {
            foreach ($log['extra'] as $key => $value) {
                $attributes[] = [
                    'key'   => 'extra.' . $key,
                    'value' => $this->convertValue($value),
                ];
            }
        }

        if (isset($log['channel'])) {
            $attributes[] = [
                'key'   => 'logger.name',
                'value' => ['stringValue' => $log['channel']],
            ];
        }

        return $attributes;
    }

    private function convertValue(mixed $value): array
    {
        if (is_string($value)) {
            return ['stringValue' => $value];
        }

        if (is_int($value)) {
            return ['intValue' => (string) $value];
        }

        if (is_float($value)) {
            return ['doubleValue' => $value];
        }

        if (is_bool($value)) {
            return ['boolValue' => $value];
        }

        if (is_array($value) || is_object($value)) {
            return ['stringValue' => json_encode($value)];
        }

        return ['stringValue' => (string) $value];
    }
}
