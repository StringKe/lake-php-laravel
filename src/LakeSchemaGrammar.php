<?php

namespace StringKe\LakePhp\Laravel;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use LogicException;
use StringKe\LakePhp\Laravel\Concerns\CompilesLakeModifiers;
use StringKe\LakePhp\Laravel\Concerns\CompilesLakeTypes;
use StringKe\LakePhp\Laravel\Concerns\WrapsLakeIdentifiers;

class LakeSchemaGrammar extends Grammar
{
    use CompilesLakeModifiers;
    use CompilesLakeTypes;
    use WrapsLakeIdentifiers;

    protected $modifiers = ['Unsigned', 'Charset', 'Collate', 'Nullable', 'Default', 'OnUpdate', 'VirtualAs', 'StoredAs', 'Invisible', 'Comment', 'After', 'First'];

    public function compileSchemas()
    {
        return 'select `name`, `name` = database() as `default` from system.databases order by `name`';
    }

    public function compileTableExists($schema, $table)
    {
        return "select count(*) > 0 as `exists` from system.tables where `database` = {$this->schemaValue($schema)} and `name` = {$this->quoteLiteral($table)} and table_type = 'BASE TABLE'";
    }

    public function compileTables($schema)
    {
        return "select `name`, `database` as `schema`, data_size as `size`, `comment`, `engine` from system.tables where table_type = 'BASE TABLE' and {$this->schemaClause($schema)} order by `database`, `name`";
    }

    public function compileViews($schema)
    {
        return "select `name`, `database` as `schema`, view_query as `definition` from system.views where {$this->schemaClause($schema)} order by `database`, `name`";
    }

    public function compileColumns($schema, $table)
    {
        return "select `name`, data_type, is_nullable, default_kind, default_expression, `comment` from system.columns where `database` = {$this->schemaValue($schema)} and `table` = {$this->quoteLiteral($table)}";
    }

    public function compileIndexes($schema, $table)
    {
        return "select `name`, `type`, `definition` from system.indexes where `database` = {$this->schemaValue($schema)} and `table` = {$this->quoteLiteral($table)} order by `name`";
    }

    public function compileForeignKeys($schema, $table)
    {
        return 'select 1 as `name` limit 0';
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command): string
    {
        $kind = match (true) {
            $blueprint->temporary => 'temp ',
            $blueprint instanceof LakeBlueprint && $blueprint->transient => 'transient ',
            default => '',
        };

        return sprintf('create %stable %s (%s)', $kind, $this->wrapTable($blueprint), implode(', ', $this->getColumns($blueprint)));
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command): string
    {
        return "alter table {$this->wrapTable($blueprint)} add column {$this->getColumn($blueprint, $this->column($command))}";
    }

    /**
     * @param  Fluent<string, mixed>  $command
     * @return list<string>
     */
    public function compileChange(Blueprint $blueprint, Fluent $command): array
    {
        $column = $this->column($command);
        $statements = ["alter table {$this->wrapTable($blueprint)} modify column {$this->getColumn($blueprint, $column)}"];

        if ($column->filled('renameTo')) {
            $statements[] = "alter table {$this->wrapTable($blueprint)} rename column {$this->wrap($column)} to {$this->wrap($column->string('renameTo')->value())}";
        }

        return $statements;
    }

    /**
     * @param  Fluent<string, mixed>  $command
     * @return list<string>
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command): array
    {
        return array_values(array_map(fn (string $column): string => "alter table {$this->wrapTable($blueprint)} drop column {$this->wrap($column)}", $command->array('columns')));
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command): string
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command): string
    {
        return 'drop table if exists '.$this->wrapTable($blueprint);
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileRename(Blueprint $blueprint, Fluent $command): string
    {
        return "alter table {$this->wrapTable($blueprint)} rename to {$this->wrap($command->string('to')->value())}";
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileTableComment(Blueprint $blueprint, Fluent $command): string
    {
        return "alter table {$this->wrapTable($blueprint)} comment = {$this->quoteLiteral($command->string('comment')->value())}";
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileClusterBy(Blueprint $blueprint, Fluent $command): string
    {
        return "alter table {$this->wrapTable($blueprint)} cluster by ({$this->columnize($command->array('columns'))})";
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileDropClusterKey(Blueprint $blueprint, Fluent $command): string
    {
        return "alter table {$this->wrapTable($blueprint)} drop cluster key";
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileFulltext(Blueprint $blueprint, Fluent $command): string
    {
        return "create inverted index {$this->wrap($command->string('index')->value())} on {$this->wrapTable($blueprint)} ({$this->columnize($command->array('columns'))})";
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileRefreshInvertedIndex(Blueprint $blueprint, Fluent $command): string
    {
        return "refresh inverted index {$this->wrap($command->string('index')->value())} on {$this->wrapTable($blueprint)}";
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileDropFullText(Blueprint $blueprint, Fluent $command): string
    {
        return "drop inverted index {$this->wrap($command->string('index')->value())} on {$this->wrapTable($blueprint)}";
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileNgramIndex(Blueprint $blueprint, Fluent $command): string
    {
        return "create ngram index {$this->wrap($command->string('index')->value())} on {$this->wrapTable($blueprint)} ({$this->columnize($command->array('columns'))})";
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileDropNgramIndex(Blueprint $blueprint, Fluent $command): string
    {
        return "drop ngram index {$this->wrap($command->string('index')->value())} on {$this->wrapTable($blueprint)}";
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command): never
    {
        throw new LogicException('Lake has no primary keys; declare the sort order with clusterBy().');
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command): never
    {
        throw new LogicException('Lake has no unique constraints; deduplicate with upsert() (MERGE) or REPLACE INTO.');
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command): never
    {
        throw new LogicException('Lake has no B-tree indexes; use clusterBy(), fullText() or ngramIndex().');
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command): never
    {
        throw new LogicException('Lake does not support spatialIndex().');
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command): never
    {
        throw new LogicException('Lake has no foreign keys.');
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    public function compileRenameIndex(Blueprint $blueprint, Fluent $command): never
    {
        throw new LogicException('Lake cannot rename indexes; drop and recreate the index.');
    }

    /**
     * @param  Fluent<string, mixed>  $command
     */
    private function column(Fluent $command): ColumnDefinition
    {
        $column = $command->get('column');

        if (! $column instanceof ColumnDefinition) {
            throw new LogicException('The schema command has no column definition.');
        }

        return $column;
    }

    /**
     * @param  string|array<string>|null  $schema
     */
    private function schemaClause(string|array|null $schema): string
    {
        return match (true) {
            is_array($schema) && $schema !== [] => '`database` in ('.implode(', ', array_map($this->quoteLiteral(...), $schema)).')',
            is_string($schema) && $schema !== '' => '`database` = '.$this->quoteLiteral($schema),
            default => "`database` not in ('system', 'information_schema')",
        };
    }

    private function schemaValue(?string $schema): string
    {
        return $schema === null || $schema === '' ? 'database()' : $this->quoteLiteral($schema);
    }
}
