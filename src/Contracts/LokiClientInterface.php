<?php

namespace Darkton\Loki\Contracts;

interface LokiClientInterface
{
    /**
     * Send a batch of log entries to Loki via OTLP.
     *
     * @param  array  $logs  Array of log entries (each as associative array).
     *
     * @throws \Darkton\Loki\Exceptions\LokiPayloadException
     * @throws \Darkton\Loki\Exceptions\LokiConnectionException
     * @throws \Darkton\Loki\Exceptions\LokiAuthenticationException
     * @throws \Darkton\Loki\Exceptions\LokiServerException
     */
    public function send(array $logs): bool;
}
