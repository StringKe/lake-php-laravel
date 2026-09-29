<?php

namespace StringKe\LakePhp\Laravel;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Fluent;

class LakeBlueprint extends Blueprint
{
    public bool $transient = false;

    public function transient(): static
    {
        $this->transient = true;

        return $this;
    }

    /**
     * An inverted index only covers blocks written after it is created; the REFRESH that follows indexes the existing rows.
     *
     * @param  list<string>|string  $columns
     */
    public function fullText($columns, $name = null, $algorithm = null)
    {
        $command = parent::fullText($columns, $name, $algorithm);
        $this->addCommand('refreshInvertedIndex', ['index' => $command->get('index')]);

        return $command;
    }

    /**
     * @param  list<string>|string  $columns
     * @return Fluent<string, mixed>
     */
    public function clusterBy(array|string $columns): Fluent
    {
        return $this->addCommand('clusterBy', ['columns' => (array) $columns]);
    }

    /**
     * @return Fluent<string, mixed>
     */
    public function dropClusterKey(): Fluent
    {
        return $this->addCommand('dropClusterKey');
    }

    /**
     * @param  list<string>|string  $columns
     * @return Fluent<string, mixed>
     */
    public function ngramIndex(array|string $columns, ?string $name = null): Fluent
    {
        return $this->indexCommand('ngramIndex', $columns, $name);
    }

    /**
     * @param  list<string>|string  $index
     * @return Fluent<string, mixed>
     */
    public function dropNgramIndex(array|string $index): Fluent
    {
        return $this->dropIndexCommand('dropNgramIndex', 'ngramIndex', $index);
    }

    public function variant(string $column): ColumnDefinition
    {
        return $this->addColumn('variant', $column);
    }

    /**
     * @param  string  $of  element type as a Lake type name, such as STRING or INT64
     */
    public function array(string $column, string $of): ColumnDefinition
    {
        return $this->addColumn('array', $column, ['of' => $of]);
    }

    public function map(string $column, string $key, string $value): ColumnDefinition
    {
        return $this->addColumn('map', $column, ['key' => $key, 'value' => $value]);
    }

    /**
     * @param  list<string>  $types
     */
    public function tuple(string $column, array $types): ColumnDefinition
    {
        return $this->addColumn('tuple', $column, ['types' => $types]);
    }

    public function bitmap(string $column): ColumnDefinition
    {
        return $this->addColumn('bitmap', $column);
    }
}
