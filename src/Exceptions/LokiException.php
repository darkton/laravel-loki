<?php

namespace Zie\Loki\Exceptions;

use Exception;

abstract class LokiException extends Exception
{
    protected array $context = [];

    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        $this->context = $context;

        parent::__construct($this->buildMessage($message), 0, $previous);
    }

    public function getContext(): array
    {
        return array_merge($this->context, [
            'exception_class' => static::class,
            'exception_type'  => $this->getType(),
        ]);
    }

    abstract public function getType(): string;

    public function isRetryable(): bool
    {
        return true;
    }

    protected function buildMessage(string $message): string
    {
        $contextString = $this->formatContextForMessage();

        return $contextString
            ? sprintf('%s | Context: %s', $message, $contextString)
            : $message;
    }

    protected function formatContextForMessage(): string
    {
        if (empty($this->context)) {
            return '';
        }

        $parts = [];
        foreach ($this->context as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = sprintf('%s=%s', $key, $value);
            } elseif (is_array($value)) {
                $parts[] = sprintf('%s=[%d items]', $key, count($value));
            }
        }

        return implode(', ', $parts);
    }
}
