<?php

namespace Darkton\Loki\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Darkton\Loki\Providers\LokiServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Get the package service providers.
     */
    protected function getPackageProviders($app): array
    {
        return [LokiServiceProvider::class];
    }

    /**
     * Define base environment for tests.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('app.name', 'TestApp');
        $app['config']->set('app.env', 'testing');
    }
}
