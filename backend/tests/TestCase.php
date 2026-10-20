<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        // Tests use phpunit.xml, never deployment secrets from a developer's .env.
        $app->loadEnvironmentFrom('tests/environment');
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();
        $testDatabase = $app['config']->get('database.connections.mariadb.database');
        if ($testDatabase !== 'potsandplanters_test') {
            throw new \LogicException('Refusing to run Laravel tests against a database other than potsandplanters_test.');
        }
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        return $app;
    }
}
