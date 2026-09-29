<?php

namespace StringKe\LakePhp\Laravel\Concerns;

use BackedEnum;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use LogicException;

/**
 * Nullability is always written out. Modifiers Lake does not have are rejected.
 */
trait CompilesLakeModifiers
{
    protected function modifyUnsigned(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->boolean('unsigned') ? ' unsigned' : '';
    }

    protected function modifyCharset(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $this->reject($column->filled('charset'), "Lake has no column character sets ({$column->string('name')}).");
    }

    protected function modifyCollate(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $this->reject($column->filled('collation'), "Lake has no column collations ({$column->string('name')}).");
    }

    protected function modifyNullable(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->boolean('nullable') ? ' null' : ' not null';
    }

    protected function modifyDefault(Blueprint $blueprint, ColumnDefinition $column): string
    {
        if ($column->get('default') !== null) {
            return ' default '.$this->getDefaultValue($column->get('default'));
        }

        return $column->boolean('useCurrent') ? ' default current_timestamp' : '';
    }

    protected function modifyOnUpdate(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $this->reject($column->filled('onUpdate') || $column->boolean('useCurrentOnUpdate'), "Lake has no ON UPDATE ({$column->string('name')}); write the value explicitly.");
    }

    protected function modifyVirtualAs(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->filled('virtualAs') ? ' as ('.$this->expression($column->get('virtualAs')).') virtual' : '';
    }

    protected function modifyStoredAs(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->filled('storedAs') ? ' as ('.$this->expression($column->get('storedAs')).') stored' : '';
    }

    protected function modifyInvisible(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $this->reject($column->boolean('invisible'), "Lake has no invisible columns ({$column->string('name')}).");
    }

    protected function modifyComment(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->get('comment') === null ? '' : ' comment '.$this->quoteLiteral($column->string('comment')->value());
    }

    protected function modifyAfter(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->filled('after') ? ' after '.$this->wrap($column->string('after')->value()) : '';
    }

    protected function modifyFirst(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->boolean('first') ? ' first' : '';
    }

    protected function getDefaultValue($value)
    {
        return match (true) {
            $value instanceof Expression => $this->expression($value),
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            $value instanceof BackedEnum => $this->quoteLiteral((string) $value->value),
            is_string($value) => $this->quoteLiteral($value),
            default => throw new LogicException('Lake column defaults must be a scalar, a backed enum or an Expression.'),
        };
    }

    private function expression(mixed $value): string
    {
        return $value instanceof Expression ? (string) $this->getValue($value) : (is_string($value) ? $value : throw new LogicException('Generated column expressions must be a string or an Expression.'));
    }

    private function reject(bool $unsupported, string $message): string
    {
        if ($unsupported) {
            throw new LogicException($message);
        }

        return '';
    }
}
