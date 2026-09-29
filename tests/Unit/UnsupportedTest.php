<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('schema features Lake does not have are rejected while compiling', function (Closure $definition): void {
    expect(fn () => Schema::connection('lake')->create('bad', $definition))->toThrow(LogicException::class);
})->with([
    'auto increment' => [fn (Blueprint $table) => $table->id()],
    'primary key' => [fn (Blueprint $table) => $table->string('k')->primary()],
    'unique' => [fn (Blueprint $table) => $table->string('k')->unique()],
    'index' => [fn (Blueprint $table) => $table->string('k')->index()],
    'spatial index' => [fn (Blueprint $table) => $table->geometry('k')->spatialIndex()],
    'foreign key' => [fn (Blueprint $table) => $table->foreign('p')->references('id')->on('x')],
    'enum' => [fn (Blueprint $table) => $table->enum('k', ['a'])],
    'set' => [fn (Blueprint $table) => $table->set('k', ['a'])],
    'time' => [fn (Blueprint $table) => $table->time('k')],
    'year' => [fn (Blueprint $table) => $table->year('k')],
    'on update' => [fn (Blueprint $table) => $table->timestamp('k')->useCurrentOnUpdate()],
    'charset' => [fn (Blueprint $table) => $table->string('k')->charset('utf8mb4')],
    'invisible' => [fn (Blueprint $table) => $table->string('k')->invisible()],
]);

test('query features Lake does not have are rejected while compiling', function (Closure $query): void {
    expect(fn () => $query(DB::connection('lake')->table('t')))->toThrow(LogicException::class);
})->with([
    'row lock' => [fn (Builder $table) => $table->lockForUpdate()->toSql()],
    'update with join' => [fn (Builder $table) => $table->getGrammar()->compileUpdate($table->join('u', 'u.id', '=', 't.id'), ['t.x' => 1])],
    'json path update' => [fn (Builder $table) => $table->getGrammar()->compileUpdate($table, ['data->k' => 1])],
    'delete with join' => [fn (Builder $table) => $table->getGrammar()->compileDelete($table->join('u', 'u.id', '=', 't.id'))],
    'insertGetId' => [fn (Builder $table) => $table->getProcessor()->processInsertGetId($table, 'insert', [])],
]);
