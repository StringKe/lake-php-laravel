<?php

namespace StringKe\LakePhp\Laravel;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;

class LakeSchemaBuilder extends Builder
{
    public function __construct(Connection $connection)
    {
        parent::__construct($connection);

        $this->blueprintResolver(fn (Connection $connection, string $table, ?Closure $callback): LakeBlueprint => new LakeBlueprint($connection, $table, $callback));
    }

    public function getCurrentSchemaListing()
    {
        return [$this->connection->getDatabaseName()];
    }

    /**
     * system.columns does not expose generated column expressions, so they are read back from SHOW CREATE TABLE.
     */
    public function getColumns($table)
    {
        $generated = $this->generatedColumns($table);

        return array_map(fn (array $column): array => [...$column, 'generation' => $generated[$column['name']] ?? null], parent::getColumns($table));
    }

    /**
     * @return array<string, array{type: string, expression: string}>
     */
    private function generatedColumns(string $table): array
    {
        $definition = (string) data_get((array) $this->connection->selectOne('show create table '.$this->grammar->wrapTable($table)), 'Create Table');
        preg_match_all('/^\s*`?([^`\s]+)`?\s.*\sAS\s\((.*)\)\s(VIRTUAL|STORED),?$/mi', $definition, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $match): array => [$match[1] => ['type' => strtolower($match[3]), 'expression' => $match[2]]])->all();
    }

    public function dropAllTables()
    {
        foreach ($this->getTableListing($this->getCurrentSchemaName(), false) as $table) {
            $this->connection->statement('drop table if exists '.$this->grammar->wrapTable($table));
        }
    }

    public function dropAllViews()
    {
        foreach ($this->getViews($this->getCurrentSchemaName()) as $view) {
            $this->connection->statement('drop view if exists '.$this->grammar->wrapTable($view['name']));
        }
    }

    public function enableForeignKeyConstraints()
    {
        return true;
    }

    public function disableForeignKeyConstraints()
    {
        return true;
    }
}
