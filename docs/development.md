# Development

## Layout

| Path | Content |
|---|---|
| `src/` | connection, connector, grammars, schema builder, blueprint, processor and service provider |
| `src/Concerns/` | identifier quoting, column types and column modifiers shared by the grammars |
| `tests/Unit/` | tests that compile SQL or build DSNs without a server |
| `tests/Integration/` | tests against a real server, each in its own database |
| `compose.yaml` | local Databend and S3 compatible storage for the tests |
| `docs/` | documentation |

The `pdo_lake` extension has to be loaded. Build it from [lake-php](https://github.com/stringke/lake-php).

## Local server

`compose.yaml` starts the same Databend and [RustFS](https://github.com/rustfs/rustfs) setup as lake-php, so stage `GET` and presigned URLs work.

```sh
docker compose up -d --wait
```

`docker compose down -v` removes everything.

## Checks

```sh
composer install
composer lint
composer typecheck
composer test
```

`composer format` applies the Pint fixes.

## Tests

The integration tests read their connection from the environment and default to the compose setup:

| Variable | Default |
|---|---|
| `LAKE_LARAVEL_TEST_HOST` | `127.0.0.1` |
| `LAKE_LARAVEL_TEST_PORT` | `8000` |
| `LAKE_LARAVEL_TEST_USER` | `lake` |
| `LAKE_LARAVEL_TEST_PASSWORD` | `lake` |
| `LAKE_LARAVEL_TEST_SSLMODE` | `disable` |
| `LAKE_LARAVEL_TEST_WAREHOUSE` | empty |
| `LAKE_LARAVEL_TEST_DATABASE_PREFIX` | `lake_laravel_test_` |

Each integration test creates a database named with the prefix and a random suffix, and drops it afterwards. The test user needs `CREATE DATABASE`. TiDB Cloud Lake requires `LAKE_LARAVEL_TEST_WAREHOUSE`.

Integration tests are skipped when the server cannot be reached. `vendor/bin/pest --testsuite=Unit` runs only the offline tests, and `--parallel` is safe because every test has its own database and stage directory.

## Releases

1. Update `CHANGELOG.md`.
2. Run the checks against the compose server and against TiDB Cloud Lake.
3. Tag `v<version>` and push the tag.

## Commits

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/) in English, for example `fix(query): quote JSON keys containing quotes` or `docs: document connection settings`. Scopes in use: `connection`, `query`, `schema`, `tests`, `build`, `docs`.
