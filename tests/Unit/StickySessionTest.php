<?php

use Illuminate\Database\QueryException;
use StringKe\LakePhp\Laravel\LakeConnection;

$scene = function (array $failures): array {
    $connection = new class(fn () => null, 'default', '', ['name' => 'lake', 'driver' => 'lake']) extends LakeConnection
    {
        public int $reconnects = 0;

        public int $calls = 0;

        public function attempt(string $sql, Closure $callback): mixed
        {
            return $this->run($sql, [], $callback);
        }
    };

    $connection->setReconnector(function () use ($connection): void {
        $connection->reconnects++;
    });

    $callback = function () use ($connection, &$failures): string {
        $connection->calls++;

        if ($failures !== []) {
            throw array_shift($failures);
        }

        return 'rows';
    };

    return [$connection, $callback];
};

$lost = fn (): PDOException => new PDOException("SQLSTATE[HY000]: General error: 400 Some(400) BadArguments. Code: 1006, Text = Sticky session state lost: node 'zlo1JshXNoSa2p0b43idU1' not found in cluster");

test('a read whose warehouse node left the cluster runs again once on a fresh session', function (string $sql) use ($scene, $lost): void {
    [$connection, $callback] = $scene([$lost()]);

    expect($connection->attempt($sql, $callback))->toBe('rows')
        ->and($connection->calls)->toBe(2)
        ->and($connection->reconnects)->toBe(1);
})->with([
    'select' => ['select count(*) from t'],
    'with' => ['  WITH x AS (SELECT 1) SELECT * FROM x'],
    'show' => ['SHOW TABLES'],
]);

test('a second lost node on the retry is thrown', function () use ($scene, $lost): void {
    [$connection, $callback] = $scene([$lost(), $lost()]);

    expect(fn () => $connection->attempt('select 1', $callback))->toThrow(QueryException::class, 'Sticky session state lost')
        ->and($connection->calls)->toBe(2)
        ->and($connection->reconnects)->toBe(1);
});

test('writes on a lost node are not repeated', function (string $sql) use ($scene, $lost): void {
    [$connection, $callback] = $scene([$lost()]);

    expect(fn () => $connection->attempt($sql, $callback))->toThrow(QueryException::class, 'Sticky session state lost')
        ->and($connection->calls)->toBe(1)
        ->and($connection->reconnects)->toBe(0);
})->with([
    'insert' => ['INSERT INTO t VALUES'],
    'stage upload' => ['PUT @~/lake/a.ndjson'],
    'copy' => ['COPY INTO t FROM @~/lake/'],
]);

test('other query errors are not retried', function () use ($scene): void {
    [$connection, $callback] = $scene([new PDOException('SQLSTATE[22000]: Data exception: 1006 parameter substitution failed')]);

    expect(fn () => $connection->attempt('select 1', $callback))->toThrow(QueryException::class, 'parameter substitution failed')
        ->and($connection->calls)->toBe(1)
        ->and($connection->reconnects)->toBe(0);
});
