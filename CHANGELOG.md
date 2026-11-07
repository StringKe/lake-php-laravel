# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Fixed

- A read outside a transaction that fails with `Sticky session state lost` (its warehouse node left the cluster) reconnects and runs again once. Writes still throw.

## [0.1.0] - 2026-09-29

First release.

### Added

- `lake` database driver for Laravel 13 on top of `pdo_lake`, registered through package discovery.
- Connector that writes connection keys and server settings into the DSN, so settings survive reconnects.
- Query grammar with Lake date functions, `ilike`, JSON path access and JSON functions, `match()` full text search, seeded random order and `upsert()` as `MERGE INTO`.
- Schema grammar and builder with transient and temporary tables, cluster keys, table and column comments, column `ALTER` operations, inverted and ngram indexes, generated columns and the Lake column types `variant`, `array`, `map`, `tuple`, `bitmap`, `vector`, `geometry` and `geography`.
- Introspection of tables, views, columns and indexes through the `system` tables.
- `LakeConnection` methods for stage transfers, bulk loads, presigned URLs, session switches, `killQuery()`, query ids and statement statistics, all running through Laravel's query pipeline.
- Compile-time `LogicException` for every Laravel feature Lake does not have, including nested transactions.

[0.1.0]: https://github.com/stringke/lake-php-laravel/releases/tag/v0.1.0
