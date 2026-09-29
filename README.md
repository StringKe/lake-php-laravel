# lake-php-laravel

Laravel database driver for [TiDB Cloud Lake](https://www.pingcap.com/) and [Databend](https://databend.com/), built on the [`pdo_lake`](https://github.com/stringke/lake-php) extension. It adds a `lake` connection driver with its own query grammar, schema grammar, schema builder and post processor, and exposes the `Pdo\Lake` features on the connection.

```php
// config/database.php
'lake' => [
    'driver' => 'lake',
    'host' => env('LAKE_HOST', '127.0.0.1'),
    'port' => env('LAKE_PORT', 8000),
    'database' => env('LAKE_DATABASE', 'default'),
    'username' => env('LAKE_USERNAME'),
    'password' => env('LAKE_PASSWORD'),
    'sslmode' => env('LAKE_SSLMODE', 'enable'),
    'warehouse' => env('LAKE_WAREHOUSE'),
    'settings' => ['timezone' => 'UTC'],
],
```

```php
DB::connection('lake')->table('events')
    ->where('properties->plan', 'pro')
    ->whereDate('time', '2026-09-28')
    ->count();
```

## Requirements

- PHP 8.4 or newer with the `pdo_lake` extension loaded. Development and the test suite run on PHP 8.5.
- Laravel 13 (`illuminate/database` and `illuminate/support`).

## Install

```sh
composer require stringke/lake-php-laravel
```

The service provider is registered through package discovery.

## Configuration

| Key | Meaning |
|---|---|
| `host`, `port`, `database`, `username`, `password` | connection target and credentials |
| `protocol`, `sslmode`, `warehouse`, `tenant`, `connect_timeout` | written into the DSN as is |
| `settings` | server session settings, for example `timezone` or `max_execute_time_in_seconds`; they travel in the DSN and apply again after a reconnect |
| `role` | role switched to right after connecting |
| `options` | PDO options |

Values containing `;`, non-scalar values and setting names outside `[A-Za-z0-9_]` are rejected, because they would change the meaning of the DSN.

## Queries

- Identifiers are quoted with backticks, and timestamps are bound with microseconds.
- `whereLike()` uses `ilike` unless `caseSensitive` is set.
- `whereDate()`, `whereTime()`, `whereDay()`, `whereMonth()` and `whereYear()` use Lake date functions.
- JSON paths compile to `col['a'][0]::STRING`. `whereJsonContains()`, `whereJsonOverlaps()`, `whereJsonContainsKey()` and `whereJsonLength()` use the Lake JSON functions.
- `whereFullText()` compiles to `match()` and needs an inverted index.
- `upsert()` compiles to `MERGE INTO`. Matched rows only change the update columns, and rows without a match are inserted.
- `inRandomOrder($seed)` uses `rand($seed)`.
- Transactions map to `BEGIN`, `COMMIT` and `ROLLBACK`.

## Schema

- `create()` supports `temporary()`, `transient()`, `clusterBy()` and `comment()`.
- `table()` supports adding, changing, renaming and dropping columns, with `after()` and `first()`. `rename()` renames tables.
- `fullText()` creates an inverted index and refreshes it, so existing rows are searchable. `ngramIndex()` and `dropNgramIndex()` manage ngram indexes. `clusterBy()` and `dropClusterKey()` manage the cluster key.
- Column types: the Laravel types that exist in Lake, plus `variant()`, `array()`, `map()`, `tuple()`, `bitmap()`, `vector()`, `geometry()` and `geography()`.
- `getTables()`, `getViews()`, `getColumns()` and `getIndexes()` read the `system` tables. Generated columns are read back from `SHOW CREATE TABLE`.

## Driver features

`LakeConnection` runs every driver operation through `run()`. Driver operations are therefore logged, dispatch `QueryExecuted`, fail with `QueryException` and are skipped under `pretend()`.

| Method | Purpose |
|---|---|
| `lake()` | the underlying `Pdo\Lake` |
| `uploadToStage()`, `uploadFileToStage()`, `putFiles()`, `getFiles()` | stage transfers |
| `loadData()`, `loadFile()`, `streamLoad()` | bulk loads, returning server statistics |
| `presign()` | presigned upload and download URLs |
| `useDatabase()`, `useWarehouse()`, `useRole()`, `setSessionSetting()` | session switches |
| `killQuery()`, `lastQueryId()`, `serverInfo()` | query control and connection info |
| `lastStatementQueryId()`, `lastStatementStats()` | query id and statistics of the last prepared statement |

## Not supported

These throw a `LogicException` while compiling. Nothing is skipped silently.

- Primary keys, unique constraints, B-tree and spatial indexes, foreign keys and index renames.
- Auto-increment columns and `insertGetId()`.
- `ENUM`, `SET`, `TIME` and `YEAR` columns, `ON UPDATE`, column character sets and collations, and invisible columns.
- Row locks, `UPDATE` and `DELETE` with joins, and in-place JSON path updates.
- Nested transactions, because Lake has no savepoints.

## Development

See [docs/development.md](docs/development.md).

## License

Apache License 2.0, see [LICENSE](LICENSE).
