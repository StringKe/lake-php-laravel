<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use StringKe\LakePhp\Laravel\LakeBlueprint;

beforeEach(function (): void {
    $this->ids = fn (Builder $query): array => array_map(intval(...), $query->orderBy('id')->pluck('id')->all());
    $this->items = fn (): Builder => $this->lake->table('items');

    $this->lake->getSchemaBuilder()->create('items', function (LakeBlueprint $table): void {
        $table->bigInteger('id');
        $table->string('name')->nullable();
        $table->string('note')->nullable();
        $table->variant('data')->nullable();
        $table->timestamp('at')->nullable();
    });

    $this->lake->statement("INSERT INTO items (id, name, note, data, at) VALUES
        (1, 'Alpha', 'n1', parse_json('{\"k\":{\"s\":\"x\",\"f\":true,\"a\":[1,2]}}'), '2026-09-28 10:11:12.123456'),
        (2, 'beta', 'n2', parse_json('{\"k\":{\"s\":\"y\",\"f\":false,\"a\":[3]}}'), '2026-10-03 23:00:00')");
});

test('JSON path values, booleans, contains, overlaps, key existence and array length', function (): void {
    $items = $this->items;

    expect(($this->ids)($items()->where('data->k->s', 'x')))->toBe([1])
        ->and(($this->ids)($items()->where('data->k->f', true)))->toBe([1])
        ->and(($this->ids)($items()->where('data->k->a[0]', '3')))->toBe([2])
        ->and(($this->ids)($items()->whereJsonContains('data->k->a', 2)))->toBe([1])
        ->and(($this->ids)($items()->whereJsonDoesntContain('data->k->a', 2)))->toBe([2])
        ->and(($this->ids)($items()->whereJsonOverlaps('data->k->a', [3, 9])))->toBe([2])
        ->and(($this->ids)($items()->whereJsonContainsKey('data->k->s')))->toBe([1, 2])
        ->and(($this->ids)($items()->whereJsonDoesntContainKey('data->k->zz')))->toBe([1, 2])
        ->and(($this->ids)($items()->whereJsonContainsKey('data->k->a[1]')))->toBe([1])
        ->and(($this->ids)($items()->whereJsonLength('data->k->a', '>', 1)))->toBe([1]);
});

test('date parts, time of day, case sensitivity and random order', function (): void {
    $items = $this->items;

    expect(($this->ids)($items()->whereDate('at', '2026-09-28')))->toBe([1])
        ->and(($this->ids)($items()->whereYear('at', 2026)))->toBe([1, 2])
        ->and(($this->ids)($items()->whereMonth('at', 10)))->toBe([2])
        ->and(($this->ids)($items()->whereDay('at', 3)))->toBe([2])
        ->and(($this->ids)($items()->whereTime('at', '>=', '23:00:00')))->toBe([2])
        ->and(($this->ids)($items()->whereLike('name', 'alpha')))->toBe([1])
        ->and(($this->ids)($items()->whereLike('name', 'alpha', caseSensitive: true)))->toBe([])
        ->and(($this->ids)($items()->whereNotLike('name', 'b%')))->toBe([1])
        ->and($items()->inRandomOrder(7)->count())->toBe(2);
});

test('timestamps with microseconds are bound without truncation', function (): void {
    expect(($this->ids)(($this->items)()->where('at', new DateTimeImmutable('2026-09-28 10:11:12.123456'))))->toBe([1]);
});

test('upsert only changes the update columns and inserts rows that do not match', function (): void {
    $affected = ($this->items)()->upsert([
        ['id' => 1, 'name' => 'Alpha2', 'note' => 'ignored'],
        ['id' => 3, 'name' => 'gamma', 'note' => 'n3'],
    ], ['id'], ['name']);

    $rows = ($this->items)()->orderBy('id')->get(['id', 'name', 'note'])->map(fn (object $row): array => [(int) $row->id, $row->name, $row->note])->all();

    expect($affected)->toBe(2)
        ->and($rows)->toBe([[1, 'Alpha2', 'n1'], [2, 'beta', 'n2'], [3, 'gamma', 'n3']]);
});

test('rolled back writes are invisible, nested transactions are refused, update, delete and truncate apply', function (): void {
    expect(fn () => $this->lake->transaction(function (): void {
        ($this->items)()->insert(['id' => 9, 'name' => 'tmp']);

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    expect(fn () => $this->lake->transaction(fn () => $this->lake->transaction(fn () => ($this->items)()->insert(['id' => 8]))))->toThrow(LogicException::class, 'nested')
        ->and($this->lake->transactionLevel())->toBe(0)
        ->and(($this->ids)(($this->items)()->whereIn('id', [8, 9])))->toBe([])
        ->and(($this->items)()->where('id', 2)->update(['note' => 'u']))->toBe(1)
        ->and(($this->items)()->where('id', 2)->value('note'))->toBe('u')
        ->and(($this->items)()->where('id', 2)->delete())->toBe(1);

    ($this->items)()->truncate();

    expect(($this->items)()->count())->toBe(0);
});

test('server errors surface as QueryException naming the connection', function (): void {
    expect(fn () => $this->lake->select('SELECT * FROM missing_table'))->toThrow(QueryException::class, 'Connection: lake');
});
