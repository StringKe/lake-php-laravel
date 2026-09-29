<?php

namespace StringKe\LakePhp\Laravel\Concerns;

use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Fluent;
use LogicException;

/**
 * Laravel column types without a Lake equivalent are rejected instead of mapped to an approximation.
 */
trait CompilesLakeTypes
{
    protected function typeChar(ColumnDefinition $column): string
    {
        return 'varchar';
    }

    protected function typeString(ColumnDefinition $column): string
    {
        return 'varchar';
    }

    protected function typeTinyText(ColumnDefinition $column): string
    {
        return 'varchar';
    }

    protected function typeText(ColumnDefinition $column): string
    {
        return 'varchar';
    }

    protected function typeMediumText(ColumnDefinition $column): string
    {
        return 'varchar';
    }

    protected function typeLongText(ColumnDefinition $column): string
    {
        return 'varchar';
    }

    protected function typeBigInteger(ColumnDefinition $column): string
    {
        return $this->integerType('bigint', $column);
    }

    protected function typeInteger(ColumnDefinition $column): string
    {
        return $this->integerType('int', $column);
    }

    protected function typeMediumInteger(ColumnDefinition $column): string
    {
        return $this->integerType('int', $column);
    }

    protected function typeSmallInteger(ColumnDefinition $column): string
    {
        return $this->integerType('smallint', $column);
    }

    protected function typeTinyInteger(ColumnDefinition $column): string
    {
        return $this->integerType('tinyint', $column);
    }

    protected function typeFloat(ColumnDefinition $column): string
    {
        return $column->filled('precision') && $column->integer('precision') <= 24 ? 'float' : 'double';
    }

    protected function typeDouble(ColumnDefinition $column): string
    {
        return 'double';
    }

    protected function typeDecimal(ColumnDefinition $column): string
    {
        return "decimal({$column->integer('total')}, {$column->integer('places')})";
    }

    protected function typeBoolean(ColumnDefinition $column): string
    {
        return 'boolean';
    }

    protected function typeJson(ColumnDefinition $column): string
    {
        return 'variant';
    }

    protected function typeJsonb(ColumnDefinition $column): string
    {
        return 'variant';
    }

    protected function typeVariant(ColumnDefinition $column): string
    {
        return 'variant';
    }

    protected function typeDate(ColumnDefinition $column): string
    {
        return 'date';
    }

    protected function typeDateTime(ColumnDefinition $column): string
    {
        return 'timestamp';
    }

    protected function typeTimestamp(ColumnDefinition $column): string
    {
        return 'timestamp';
    }

    protected function typeDateTimeTz(ColumnDefinition $column): string
    {
        return 'timestamp_tz';
    }

    protected function typeTimestampTz(ColumnDefinition $column): string
    {
        return 'timestamp_tz';
    }

    protected function typeBinary(ColumnDefinition $column): string
    {
        return 'binary';
    }

    protected function typeUuid(ColumnDefinition $column): string
    {
        return 'varchar';
    }

    protected function typeIpAddress(ColumnDefinition $column): string
    {
        return 'varchar';
    }

    protected function typeMacAddress(ColumnDefinition $column): string
    {
        return 'varchar';
    }

    protected function typeGeometry(ColumnDefinition $column): string
    {
        return 'geometry';
    }

    protected function typeGeography(ColumnDefinition $column): string
    {
        return 'geography';
    }

    /**
     * @param  Fluent<string, mixed>  $column
     */
    protected function typeVector(Fluent $column): string
    {
        return "vector({$column->integer('dimensions')})";
    }

    protected function typeArray(ColumnDefinition $column): string
    {
        return "array({$column->string('of')})";
    }

    protected function typeMap(ColumnDefinition $column): string
    {
        return "map({$column->string('key')}, {$column->string('value')})";
    }

    protected function typeTuple(ColumnDefinition $column): string
    {
        return 'tuple('.$column->collect('types')->implode(', ').')';
    }

    protected function typeBitmap(ColumnDefinition $column): string
    {
        return 'bitmap';
    }

    protected function typeEnum(ColumnDefinition $column): string
    {
        throw new LogicException("Lake has no ENUM type ({$column->string('name')}); use string and validate the values in the application.");
    }

    protected function typeSet(ColumnDefinition $column): string
    {
        throw new LogicException("Lake has no SET type ({$column->string('name')}); use array.");
    }

    protected function typeTime(ColumnDefinition $column): string
    {
        throw new LogicException("Lake has no TIME type ({$column->string('name')}).");
    }

    protected function typeTimeTz(ColumnDefinition $column): string
    {
        throw new LogicException("Lake has no TIME type ({$column->string('name')}).");
    }

    protected function typeYear(ColumnDefinition $column): string
    {
        throw new LogicException("Lake has no YEAR type ({$column->string('name')}); use smallInteger.");
    }

    /**
     * @param  Fluent<string, mixed>  $column
     */
    protected function typeComputed(Fluent $column): string
    {
        throw new LogicException("Lake generated columns need a declared type ({$column->string('name')}); use virtualAs or storedAs on a typed column.");
    }

    private function integerType(string $type, ColumnDefinition $column): string
    {
        if ($column->boolean('autoIncrement')) {
            throw new LogicException("Lake has no auto-increment columns ({$column->string('name')}); generate keys in the application.");
        }

        return $type;
    }
}
