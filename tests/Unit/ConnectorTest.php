<?php

use Illuminate\Support\Facades\DB;
use StringKe\LakePhp\Laravel\LakeConnection;
use StringKe\LakePhp\Laravel\LakeConnector;

test('the lake driver resolves to LakeConnection without contacting the server', function (): void {
    expect(DB::connection('lake'))->toBeInstanceOf(LakeConnection::class)
        ->and(app('db.connector.lake'))->toBeInstanceOf(LakeConnector::class);
});

test('the DSN carries connection keys and server settings and rejects values that would break it', function (): void {
    $connector = new LakeConnector;

    expect($connector->dsn(['host' => 'h', 'port' => 443, 'database' => 'd', 'sslmode' => 'enable', 'warehouse' => 'w', 'protocol' => 'http', 'role' => 'r', 'settings' => ['timezone' => 'UTC', 'enable_x' => true, 'skipped' => null]]))
        ->toBe('lake:dbname=d;host=h;port=443;protocol=http;sslmode=enable;warehouse=w;timezone=UTC;enable_x=1')
        ->and(fn () => $connector->dsn(['host' => 'h', 'settings' => ['a' => 'x;y']]))->toThrow(LogicException::class, '[a]')
        ->and(fn () => $connector->dsn(['host' => 'h', 'settings' => ['a-b' => '1']]))->toThrow(LogicException::class, '[a-b]')
        ->and(fn () => $connector->dsn(['host' => 'h', 'settings' => ['a' => ['x']]]))->toThrow(LogicException::class, '[a]');
});
