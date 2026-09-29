<?php

namespace StringKe\LakePhp\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use StringKe\LakePhp\Laravel\LakeServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [LakeServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'lake');
        $app['config']->set('database.connections.lake', [
            'driver' => 'lake',
            'host' => self::env('LAKE_LARAVEL_TEST_HOST', '127.0.0.1'),
            'port' => self::env('LAKE_LARAVEL_TEST_PORT', '8000'),
            'username' => self::env('LAKE_LARAVEL_TEST_USER', 'lake'),
            'password' => self::env('LAKE_LARAVEL_TEST_PASSWORD', 'lake'),
            'sslmode' => self::env('LAKE_LARAVEL_TEST_SSLMODE', 'disable'),
            'warehouse' => self::env('LAKE_LARAVEL_TEST_WAREHOUSE', ''),
            'database' => 'default',
            'prefix' => '',
            'settings' => ['timezone' => 'UTC'],
        ]);
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false ? $default : $value;
    }
}
