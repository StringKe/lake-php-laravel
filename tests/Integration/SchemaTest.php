<?php

use Illuminate\Database\Schema\Blueprint;
use StringKe\LakePhp\Laravel\LakeBlueprint;
use StringKe\LakePhp\Laravel\LakeSchemaBuilder;

beforeEach(function (): void {
    $this->schema = $this->lake->getSchemaBuilder();
});

test('create maps Laravel types to Lake types with nullability, defaults, comments, cluster key and table comment', function (): void {
    expect($this->schema)->toBeInstanceOf(LakeSchemaBuilder::class);

    $this->schema->create('orders', function (LakeBlueprint $table): void {
        $table->bigInteger('id')->unsigned()->comment('order id');
        $table->string('name')->nullable()->default('x');
        $table->decimal('amount', 36, 18)->nullable();
        $table->boolean('paid')->default(false);
        $table->json('payload')->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestampTz('seen_at')->nullable();
        $table->date('day')->nullable();
        $table->array('tags', 'STRING')->nullable();
        $table->map('attrs', 'STRING', 'INT64')->nullable();
        $table->string('lower_name')->nullable()->virtualAs('lower(name)');
        $table->clusterBy(['id']);
        $table->comment('orders');
    });

    $columns = collect($this->schema->getColumns('orders'))->keyBy('name');

    expect($this->schema->hasTable('orders'))->toBeTrue()
        ->and($columns->keys()->all())->toBe(['id', 'name', 'amount', 'paid', 'payload', 'created_at', 'seen_at', 'day', 'tags', 'attrs', 'lower_name'])
        ->and($columns['id'])->toMatchArray(['type_name' => 'bigint unsigned', 'nullable' => false, 'comment' => 'order id'])
        ->and($columns['name'])->toMatchArray(['type_name' => 'varchar', 'nullable' => true, 'default' => "'x'", 'generation' => null])
        ->and($columns['amount']['type'])->toBe('decimal(36, 18)')
        ->and($columns['paid'])->toMatchArray(['type_name' => 'boolean', 'default' => 'false'])
        ->and($columns['payload']['type_name'])->toBe('variant')
        ->and($columns['created_at']['type_name'])->toBe('timestamp')
        ->and($columns['seen_at']['type_name'])->toBe('timestamptz')
        ->and($columns['tags']['type_name'])->toBe('array')
        ->and($columns['lower_name']['generation'])->toBe(['type' => 'virtual', 'expression' => 'lower(name)']);

    $table = collect($this->schema->getTables($this->lake->getDatabaseName()))->firstWhere('name', 'orders');

    expect($table)->toMatchArray(['schema' => $this->lake->getDatabaseName(), 'comment' => 'orders', 'engine' => 'FUSE'])
        ->and($this->lake->selectOne('SELECT cluster_by FROM system.tables WHERE database = database() AND name = ?', ['orders'])->cluster_by)->toContain('id');
});

test('add, change, rename and drop columns and rename tables through ALTER', function (): void {
    $this->schema->create('items', function (Blueprint $table): void {
        $table->bigInteger('id');
        $table->integer('qty')->nullable();
    });

    $this->schema->table('items', function (Blueprint $table): void {
        $table->string('sku')->nullable()->after('id')->comment('stock keeping unit');
    });
    $this->schema->table('items', function (Blueprint $table): void {
        $table->bigInteger('qty')->nullable()->comment('quantity')->change();
    });
    $this->schema->table('items', function (Blueprint $table): void {
        $table->renameColumn('sku', 'code');
    });
    $this->schema->table('items', function (Blueprint $table): void {
        $table->dropColumn(['qty']);
    });
    $this->schema->rename('items', 'goods');

    expect($this->schema->hasTable('items'))->toBeFalse()
        ->and($this->schema->getColumnListing('goods'))->toBe(['id', 'code'])
        ->and(collect($this->schema->getColumns('goods'))->firstWhere('name', 'code')['comment'])->toBe('stock keeping unit');

    $this->schema->dropIfExists('goods');

    expect($this->schema->hasTable('goods'))->toBeFalse();
});

test('fullText builds a refreshed inverted index over existing rows, ngram indexes and drops work', function (): void {
    $this->schema->create('docs', function (Blueprint $table): void {
        $table->bigInteger('id');
        $table->string('body')->nullable();
    });
    $this->lake->table('docs')->insert([['id' => 1, 'body' => 'Hello Lake'], ['id' => 2, 'body' => 'other']]);

    $this->schema->table('docs', function (LakeBlueprint $table): void {
        $table->fullText('body');
        $table->ngramIndex('body');
    });

    expect(collect($this->schema->getIndexes('docs'))->map(fn (array $index): array => [$index['name'], $index['type'], $index['columns']])->all())
        ->toBe([['docs_body_fulltext', 'inverted', ['body']], ['docs_body_ngramindex', 'ngram', ['body']]])
        ->and($this->lake->table('docs')->whereFullText('body', 'hello')->pluck('id')->all())->toBe([1]);

    $this->schema->table('docs', function (LakeBlueprint $table): void {
        $table->dropFullText(['body']);
        $table->dropNgramIndex(['body']);
    });

    expect($this->schema->getIndexes('docs'))->toBe([]);
});

test('views and tables are listed and dropped', function (): void {
    $this->schema->create('a', fn (Blueprint $table) => $table->integer('x')->nullable());
    $this->lake->statement('CREATE VIEW v_a AS SELECT x FROM a');

    expect($this->schema->hasView('v_a'))->toBeTrue()
        ->and($this->schema->getViews($this->lake->getDatabaseName())[0]['definition'])->toMatch('/SELECT x FROM (\w+\.)?a$/');

    $this->schema->dropAllViews();
    $this->schema->dropAllTables();

    expect($this->schema->getTables($this->lake->getDatabaseName()))->toBe([])
        ->and($this->schema->getViews($this->lake->getDatabaseName()))->toBe([]);
});
