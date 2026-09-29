<?php

namespace StringKe\LakePhp\Laravel;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use PDO;

class LakeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('db.connector.lake', LakeConnector::class);

        Connection::resolverFor('lake', fn (PDO|Closure $pdo, string $database, string $prefix, array $config): LakeConnection => new LakeConnection($pdo, $database, $prefix, $config));
    }
}
