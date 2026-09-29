<?php

namespace StringKe\LakePhp\Laravel;

use Closure;
use Illuminate\Database\Connection;
use LogicException;
use Pdo\Lake;
use PDOStatement;

/**
 * Driver-specific operations run through run(), so they are logged, dispatch QueryExecuted and fail with QueryException like any SQL statement.
 */
class LakeConnection extends Connection
{
    private ?PDOStatement $lastStatement = null;

    public function getDriverTitle()
    {
        return 'TiDB Cloud Lake';
    }

    protected function getDefaultQueryGrammar()
    {
        return new LakeQueryGrammar($this);
    }

    protected function getDefaultSchemaGrammar()
    {
        return new LakeSchemaGrammar($this);
    }

    public function getSchemaBuilder()
    {
        if ($this->schemaGrammar === null) {
            $this->useDefaultSchemaGrammar();
        }

        return new LakeSchemaBuilder($this);
    }

    protected function getDefaultPostProcessor()
    {
        return new LakeProcessor;
    }

    protected function escapeBinary($value)
    {
        return "from_hex('".bin2hex($value)."')";
    }

    protected function escapeBool($value)
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Laravel silently skips the savepoint when the grammar has none, which would turn an inner rollback into a no-op.
     */
    protected function createTransaction()
    {
        if ($this->transactions > 0) {
            throw new LogicException('Lake has no savepoints; nested transactions are not available.');
        }

        parent::createTransaction();
    }

    protected function prepared(PDOStatement $statement)
    {
        $this->lastStatement = $statement;

        return parent::prepared($statement);
    }

    public function lake(): Lake
    {
        $pdo = $this->getPdo();

        if (! $pdo instanceof Lake) {
            throw new LogicException('The connection is not a Pdo\Lake instance.');
        }

        return $pdo;
    }

    public function lastQueryId(): ?string
    {
        return $this->lake()->lastQueryId();
    }

    /**
     * Server statistics of the last statement prepared on this connection: total_rows, read_rows, write_rows, running_time_ms and more.
     *
     * @return array<mixed>|null
     */
    public function lastStatementStats(): ?array
    {
        $stats = $this->lastStatement?->getAttribute(Lake::ATTR_QUERY_STATS);

        return is_array($stats) ? $stats : null;
    }

    public function lastStatementQueryId(): ?string
    {
        $id = $this->lastStatement?->getAttribute(Lake::ATTR_QUERY_ID);

        return is_string($id) ? $id : null;
    }

    public function killQuery(string $queryId): bool
    {
        return $this->run("KILL QUERY '{$queryId}'", [], fn (): bool => $this->lake()->killQuery($queryId));
    }

    /**
     * @return array<string, mixed>
     */
    public function serverInfo(): array
    {
        return $this->ensureArray($this->lake()->info(), 'Reading connection info');
    }

    public function useWarehouse(string $warehouse): void
    {
        $this->run("USE WAREHOUSE {$warehouse}", [], fn (): bool => $this->lake()->useWarehouse($warehouse));
        $this->config['warehouse'] = $warehouse;
    }

    public function useDatabase(string $database): void
    {
        $this->run("USE {$database}", [], fn (): bool => $this->lake()->useDatabase($database));
        $this->setDatabaseName($database);
    }

    public function useRole(string $role): void
    {
        $this->run("SET ROLE {$role}", [], fn (): bool => $this->lake()->useRole($role));
        $this->config['role'] = $role;
    }

    public function setSessionSetting(string $name, string|int|bool $value): void
    {
        $value = is_bool($value) ? (string) (int) $value : (string) $value;

        $this->run("SET {$name} = '{$value}'", [], fn (): bool => $this->lake()->setSessionSetting($name, $value));
    }

    /**
     * @return array<mixed> method、headers、url
     */
    public function presign(string $operation, string $stage): array
    {
        return $this->ensureArray($this->run("PRESIGN {$operation} {$stage}", [], fn (): array|false => $this->lake()->presign($operation, $stage)), "PRESIGN {$stage}");
    }

    public function uploadToStage(string $stage, string $data): void
    {
        $this->write("PUT {$stage}", fn (): bool => $this->lake()->uploadToStage($stage, $data));
    }

    public function uploadFileToStage(string $stage, string $filename): void
    {
        $this->write("PUT {$filename} {$stage}", fn (): bool => $this->lake()->uploadFileToStage($stage, $filename));
    }

    /**
     * @return array<mixed> server load statistics
     */
    public function loadData(string $sql, string $data, int $method = Lake::LOAD_STAGE): array
    {
        return $this->load($sql, fn (): array|false => $this->lake()->loadData($sql, $data, $method));
    }

    /**
     * @param  array<string, string>|null  $fileFormatOptions
     * @param  array<string, string>|null  $copyOptions
     * @return array<mixed> server load statistics
     */
    public function loadFile(string $sql, string $filename, int $method = Lake::LOAD_STAGE, ?array $fileFormatOptions = null, ?array $copyOptions = null): array
    {
        return $this->load($sql, fn (): array|false => $this->lake()->loadFile($sql, $filename, $method, $fileFormatOptions, $copyOptions));
    }

    /**
     * @param  list<list<scalar|null>>  $rows
     * @return array<mixed> server load statistics
     */
    public function streamLoad(string $sql, array $rows, int $method = Lake::LOAD_STREAMING): array
    {
        return $this->load($sql, fn (): array|false => $this->lake()->streamLoad($sql, $rows, $method));
    }

    /**
     * @return array<mixed> one row per file with file, status and size
     */
    public function putFiles(string $localFiles, string $stage): array
    {
        return $this->load("PUT {$localFiles} {$stage}", fn (): array|false => $this->lake()->putFiles($localFiles, $stage));
    }

    /**
     * @return array<mixed> one row per file with file, status and size
     */
    public function getFiles(string $stage, string $localDirectory): array
    {
        return $this->ensureArray($this->run("GET {$stage} {$localDirectory}", [], fn (): array|false => $this->pretending() ? [] : $this->lake()->getFiles($stage, $localDirectory)), "GET {$stage}");
    }

    /**
     * @param  Closure(): bool  $callback
     */
    private function write(string $label, Closure $callback): void
    {
        $done = $this->run($label, [], function () use ($callback): bool {
            if ($this->pretending()) {
                return true;
            }

            $this->recordsHaveBeenModified();

            return $callback();
        });

        if ($done !== true) {
            throw new LogicException("{$label} failed.");
        }
    }

    /**
     * @param  Closure(): (array<mixed>|false)  $callback
     * @return array<mixed>
     */
    private function load(string $label, Closure $callback): array
    {
        return $this->ensureArray($this->run($label, [], function () use ($callback): array|false {
            if ($this->pretending()) {
                return [];
            }

            $this->recordsHaveBeenModified();

            return $callback();
        }), $label);
    }

    /**
     * @return array<mixed>
     */
    private function ensureArray(mixed $result, string $action): array
    {
        if (! is_array($result)) {
            throw new LogicException("{$action} failed.");
        }

        return $result;
    }
}
