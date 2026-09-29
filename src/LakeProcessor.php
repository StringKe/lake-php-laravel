<?php

namespace StringKe\LakePhp\Laravel;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use LogicException;

class LakeProcessor extends Processor
{
    /**
     * @param  array<mixed>  $values
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        throw new LogicException('Lake has no auto-increment keys; insertGetId is not available.');
    }

    public function processColumns($results)
    {
        return array_map(function (mixed $result): array {
            $result = (object) $result;

            return [
                'name' => $result->name,
                'type' => strtolower((string) $result->data_type),
                'type_name' => strtolower((string) preg_replace('/\(.*$/', '', (string) $result->data_type)),
                'collation' => null,
                'nullable' => $result->is_nullable === 'YES',
                'default' => $result->default_kind === 'DEFAULT' ? $result->default_expression : null,
                'auto_increment' => false,
                'comment' => $result->comment === '' ? null : $result->comment,
                'generation' => null,
            ];
        }, $results);
    }

    public function processIndexes($results)
    {
        return array_map(function (mixed $result): array {
            $result = (object) $result;
            preg_match('/\((.*)\)\s*$/', (string) $result->definition, $matches);

            return [
                'name' => $result->name,
                'columns' => array_values(array_filter(array_map(fn (string $column): string => trim($column, ' `"'), explode(',', $matches[1] ?? '')), fn (string $column): bool => $column !== '')),
                'type' => strtolower((string) $result->type),
                'unique' => false,
                'primary' => false,
            ];
        }, $results);
    }
}
