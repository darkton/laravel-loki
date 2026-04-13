<?php

namespace Zie\Loki\Contracts;

interface LokiClientInterface
{
    /**
     * Send a batch of log entries to Loki via OTLP.
     *
     * @param  array  $logs  Array of log entries (each as associative array).
     *
     * @throws \Zie\Loki\Exceptions\LokiPayloadException
     * @throws \Zie\Loki\Exceptions\LokiConnectionException
     * @throws \Zie\Loki\Exceptions\LokiAuthenticationException
     * @throws \Zie\Loki\Exceptions\LokiServerException
     */
    public function send(array $logs): bool;
}
