<?php

namespace StringKe\LakePhp\Laravel;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use LogicException;
use StringKe\LakePhp\Laravel\Concerns\WrapsLakeIdentifiers;

class LakeQueryGrammar extends Grammar
{
    use WrapsLakeIdentifiers;

    /**
     * @var list<string>
     */
    protected $operators = ['ilike', 'not ilike', 'regexp', 'not regexp', 'rlike', 'not rlike'];

    /**
     * @param  array<string, mixed>  $where
     */
    protected function whereLike(Builder $query, $where)
    {
        $where['operator'] = ($where['not'] ? 'not ' : '').($where['caseSensitive'] ? 'like' : 'ilike');

        return $this->whereBasic($query, $where);
    }

    /**
     * @param  array<string, mixed>  $where
     */
    protected function whereDate(Builder $query, $where)
    {
        return $this->dateBasedWhere('to_date', $query, $where);
    }

    /**
     * @param  array<string, mixed>  $where
     */
    protected function whereTime(Builder $query, $where)
    {
        return 'to_string('.$this->wrap($where['column']).", '%H:%M:%S') ".$where['operator'].' '.$this->parameter($where['value']);
    }

    /**
     * @param  array<string, mixed>  $where
     */
    protected function whereDay(Builder $query, $where)
    {
        return $this->dateBasedWhere('to_day_of_month', $query, $where);
    }

    /**
     * @param  array<string, mixed>  $where
     */
    protected function whereMonth(Builder $query, $where)
    {
        return $this->dateBasedWhere('to_month', $query, $where);
    }

    /**
     * @param  array<string, mixed>  $where
     */
    protected function whereYear(Builder $query, $where)
    {
        return $this->dateBasedWhere('to_year', $query, $where);
    }

    /**
     * @param  array<string, mixed>  $where
     */
    public function whereFullText(Builder $query, $where)
    {
        $columns = implode(', ', array_map(strval(...), $where['columns']));

        return 'match('.$this->quoteLiteral($columns).', '.$this->parameter($where['value']).')';
    }

    protected function wrapJsonSelector($value)
    {
        return $this->jsonAccess($value).'::STRING';
    }

    protected function wrapJsonBooleanSelector($value)
    {
        return $this->jsonAccess($value).'::BOOLEAN';
    }

    protected function compileJsonContains($column, $value)
    {
        return 'json_contains_in_left('.$this->jsonAccess($column).', parse_json('.$value.'))';
    }

    protected function compileJsonOverlaps($column, $value)
    {
        return 'json_array_overlap('.$this->jsonAccess($column).', parse_json('.$value.'))';
    }

    protected function compileJsonContainsKey($column)
    {
        [$field, $path] = $this->jsonPath($column);
        $last = array_pop($path);

        if ($last === null) {
            throw new LogicException("whereJsonContainsKey needs a JSON path: {$column}");
        }

        $parent = $this->renderJsonPath($field, $path);

        return is_int($last) ? "array_size({$parent}) > {$last}" : "json_exists_key({$parent}, {$this->quoteLiteral($last)})";
    }

    protected function compileJsonLength($column, $operator, $value)
    {
        return 'array_size('.$this->jsonAccess($column).') '.$operator.' '.$value;
    }

    /**
     * `data->a->b[0]` -> `data`['a']['b'][0], a VARIANT; callers append the cast or comparison.
     */
    protected function jsonAccess(string $value): string
    {
        return $this->renderJsonPath(...$this->jsonPath($value));
    }

    /**
     * @return array{string, list<string|int>}
     */
    private function jsonPath(string $value): array
    {
        $field = null;
        $path = [];

        foreach (explode('->', $value) as $segment) {
            preg_match_all('/\[(\d+)\]/', $segment, $indexes);
            $key = trim((string) preg_replace('/(\[\d+\])+$/', '', $segment), '"');

            if ($field === null) {
                $field = $key;
            } elseif ($key !== '') {
                $path[] = $key;
            }

            array_push($path, ...array_map(intval(...), $indexes[1]));
        }

        return [(string) $field, $path];
    }

    /**
     * @param  list<string|int>  $path
     */
    private function renderJsonPath(string $field, array $path): string
    {
        return $this->wrap($field).implode('', array_map(fn (string|int $step): string => is_int($step) ? "[{$step}]" : '['.$this->quoteLiteral($step).']', $path));
    }

    public function compileRandom($seed)
    {
        return $seed === '' ? 'rand()' : 'rand('.(int) $seed.')';
    }

    protected function compileLock(Builder $query, $value)
    {
        throw new LogicException('Lake has no row locks; lockForUpdate and sharedLock are not available.');
    }

    public function supportsSavepoints()
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function compileUpdateColumns(Builder $query, array $values)
    {
        foreach (array_keys($values) as $column) {
            if ($this->isJsonSelector((string) $column)) {
                throw new LogicException("Lake cannot update a JSON path in place ({$column}); write the whole column.");
            }
        }

        return parent::compileUpdateColumns($query, $values);
    }

    protected function compileUpdateWithJoins(Builder $query, $table, $columns, $where)
    {
        throw new LogicException('Lake UPDATE does not support joins; use a whereIn subquery.');
    }

    protected function compileDeleteWithJoins(Builder $query, $table, $where)
    {
        throw new LogicException('Lake DELETE does not support joins; use a whereIn subquery.');
    }

    /**
     * Lake has no unique constraints, so upsert is a MERGE on $uniqueBy: matched rows only change the $update columns.
     *
     * @param  array<array<string, mixed>>  $values
     * @param  array<string>  $uniqueBy
     * @param  array<int|string, mixed>  $update
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update)
    {
        $table = $this->wrapTable($query->from);
        $source = $this->wrap('laravel_source');
        $columns = array_keys($values[array_key_first($values)] ?? []);

        $rows = array_map(fn (array $row): string => 'select '.implode(', ', array_map(fn (mixed $value, string $column): string => $this->parameter($value).' as '.$this->wrap($column), $row, array_keys($row))), $values);
        $on = implode(' and ', array_map(fn (string $column): string => "{$table}.{$this->wrap($column)} = {$source}.{$this->wrap($column)}", $uniqueBy));
        $set = implode(', ', array_map(fn (mixed $value, int|string $key): string => is_int($key) ? "{$this->wrap($value)} = {$source}.{$this->wrap($value)}" : "{$this->wrap($key)} = {$this->parameter($value)}", $update, array_keys($update)));
        $insert = implode(', ', array_map(fn (string $column): string => "{$source}.{$this->wrap($column)}", $columns));

        return "merge into {$table} using (".implode(' union all ', $rows).") as {$source} on {$on} when matched then update set {$set} when not matched then insert ({$this->columnize($columns)}) values ({$insert})";
    }
}
