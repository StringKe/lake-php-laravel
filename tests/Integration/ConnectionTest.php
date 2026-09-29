<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pdo\Lake;

beforeEach(function (): void {
    $this->stage = '@~/lake_laravel_test/'.Str::lower(Str::ulid()).'/';
    $this->dir = sys_get_temp_dir().'/lake_laravel_test_'.Str::lower(Str::ulid());
    mkdir($this->dir);

    $this->lake->getSchemaBuilder()->create('t', function (Blueprint $table): void {
        $table->bigInteger('id');
        $table->string('name')->nullable();
    });
});

afterEach(function (): void {
    $this->lake->statement("REMOVE {$this->stage}");
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    rmdir($this->dir);
});

test('server settings travel in the DSN and survive a reconnect', function (): void {
    config(['database.connections.lake.settings' => ['timezone' => 'Asia/Kolkata', 'max_execute_time_in_seconds' => 1]]);
    DB::purge('lake');
    $lake = DB::connection('lake');
    $aborted = fn () => $lake->select('SELECT sleep(3) AS s');

    expect($lake->selectOne('SELECT timezone() AS tz')->tz)->toBe('Asia/Kolkata')
        ->and($aborted)->toThrow(QueryException::class, 'maximum limit');

    DB::reconnect('lake');

    expect($lake->selectOne('SELECT timezone() AS tz')->tz)->toBe('Asia/Kolkata')
        ->and($aborted)->toThrow(QueryException::class, 'maximum limit');
});

test('session switches: database, warehouse, role and settings', function (): void {
    $other = $this->lake->getDatabaseName().'_b';
    $this->lake->statement("create database `{$other}`");
    $warehouse = $this->lake->serverInfo()['warehouse'] ?? null;
    $role = (string) $this->lake->selectOne('SELECT current_role() AS r')->r;

    try {
        if (is_string($warehouse) && $warehouse !== '') {
            $this->lake->useWarehouse($warehouse);
        }

        $this->lake->useRole($role);
        $this->lake->setSessionSetting('timezone', 'Asia/Kolkata');
        $this->lake->useDatabase($other);

        expect($this->lake->getDatabaseName())->toBe($other)
            ->and($this->lake->selectOne('SELECT database() AS d')->d)->toBe($other)
            ->and($this->lake->selectOne('SELECT timezone() AS tz')->tz)->toBe('Asia/Kolkata')
            ->and($this->lake->lake()->getAttribute(Lake::ATTR_ROLE))->toBe($role);
    } finally {
        $this->lake->statement("drop database if exists `{$other}`");
    }
});

test('query ids and statement statistics are available and driver operations are query events', function (): void {
    $sql = [];
    $this->lake->listen(function (QueryExecuted $event) use (&$sql): void {
        $sql[] = $event->sql;
    });

    $this->lake->select('SELECT count(*) AS c FROM t');
    $queryId = (string) $this->lake->lastQueryId();
    $this->lake->uploadToStage($this->stage.'a.ndjson', "{\"id\":1}\n");
    $this->lake->killQuery($queryId);

    expect($queryId)->not->toBe('')
        ->and($this->lake->lastStatementQueryId())->toBe($queryId)
        ->and($this->lake->lastStatementStats())->toHaveKeys(['read_rows', 'running_time_ms'])
        ->and($sql)->toBe(['SELECT count(*) AS c FROM t', "PUT {$this->stage}a.ndjson", "KILL QUERY '{$queryId}'"]);
});

test('pretend mode records driver operations without executing them', function (): void {
    $queries = $this->lake->pretend(function (): void {
        $this->lake->uploadToStage($this->stage.'p.csv', "1,a\n");
        $this->lake->loadData('INSERT INTO t VALUES', "1,a\n");
    });

    expect(array_column($queries, 'query'))->toBe(["PUT {$this->stage}p.csv", 'INSERT INTO t VALUES'])
        ->and($this->lake->table('t')->count())->toBe(0);
});

test('string, file and row array loads land in the table', function (): void {
    file_put_contents($this->dir.'/rows.csv', "3,c\n4,d\n");

    $this->lake->loadData('INSERT INTO t VALUES', "1,a\n2,b\n");
    $this->lake->loadFile('INSERT INTO t VALUES', $this->dir.'/rows.csv');
    $stats = $this->lake->streamLoad('INSERT INTO t VALUES', [[5, 'e'], [6, null]]);

    expect($stats)->toHaveKey('write_rows')
        ->and(array_map(intval(...), $this->lake->table('t')->orderBy('id')->pluck('id')->all()))->toBe([1, 2, 3, 4, 5, 6])
        ->and($this->lake->table('t')->where('id', 6)->value('name'))->toBeNull();
});

test('local files round-trip through the stage with upload, PUT, COPY, GET and presign', function (): void {
    file_put_contents($this->dir.'/a.csv', "7,g\n");
    file_put_contents($this->dir.'/b.csv', "8,h\n");

    $this->lake->uploadFileToStage($this->stage.'up/a.csv', $this->dir.'/a.csv');
    $put = $this->lake->putFiles('file://'.$this->dir.'/b.csv', $this->stage.'put/');
    $this->lake->statement("COPY INTO t FROM {$this->stage} PATTERN = '.*[.]csv' FILE_FORMAT = (TYPE = CSV)");

    unlink($this->dir.'/a.csv');
    unlink($this->dir.'/b.csv');
    $got = $this->lake->getFiles($this->stage.'up/', 'file://'.$this->dir);
    $presigned = $this->lake->presign('DOWNLOAD', $this->stage.'up/a.csv');

    expect(array_map(intval(...), $this->lake->table('t')->orderBy('id')->pluck('id')->all()))->toBe([7, 8])
        ->and($put)->toHaveCount(1)
        ->and($got)->toHaveCount(1)
        ->and(file_get_contents($this->dir.'/a.csv'))->toBe("7,g\n")
        ->and($presigned)->toHaveKeys(['method', 'headers', 'url']);
});
