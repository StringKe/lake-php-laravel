<?php

namespace StringKe\LakePhp\Laravel\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use StringKe\LakePhp\Laravel\LakeConnection;
use Throwable;

/**
 * Every test gets its own database, dropped afterwards. Tests are skipped when the server cannot be reached.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected LakeConnection $lake;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $prefix = getenv('LAKE_LARAVEL_TEST_DATABASE_PREFIX') ?: 'lake_laravel_test_';
        $this->database = $prefix.Str::lower(Str::random(10));

        try {
            DB::connection('lake')->statement("create database `{$this->database}`");
        } catch (Throwable $e) {
            $this->markTestSkipped('Lake server not reachable: '.$e->getMessage());
        }

        config(['database.connections.lake.database' => $this->database]);
        DB::purge('lake');
        $connection = DB::connection('lake');
        $this->assertInstanceOf(LakeConnection::class, $connection);
        $this->lake = $connection;
    }

    protected function tearDown(): void
    {
        if (isset($this->lake)) {
            $this->lake->statement("drop database if exists `{$this->database}`");
        }

        parent::tearDown();
    }
}
