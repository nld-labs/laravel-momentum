<?php

use Illuminate\Pagination\LengthAwarePaginator;
use NLD\Momentum\Tests\Support\RelatedResource;
use NLD\Momentum\Tests\Support\TestModel;
use NLD\Momentum\Tests\Support\TestResource;

describe('paginated()', function () {
    test('returns instance of LengthAwarePaginator with resource array', function () {
        TestModel::create(['name' => 'first']);
        $items = TestModel::paginate(1);
        $paginated = TestResource::paginated($items);

        expect($paginated)
            ->toBeInstanceOf(LengthAwarePaginator::class);
        expect($paginated->toArray()['data'])
            ->toHaveCount(1)
            ->{0}->toBeInstanceOf(TestResource::class);
    });
});

describe('resolveData()', function () {
    beforeEach(function () {
        $this->model = TestModel::create(['name' => 'build']);
        $this->resource = TestResource::make($this->model);
    });

    it('has only allowed keys', function () {
        $this->resource->fakeBuildOptions = ['pick' => ['id', 'name']];
        $value = $this->resource->toArray(request()->create('x'));

        expect($value)
            ->toHaveKeys(['id', 'name'])
            ->not->toHaveKeys(['created_at', 'updated_at'])
            ->toMatchArray([
                'id' => 1,
                'name' => 'build',
            ]);
    });

    it('has all keys except excluded', function () {
        $this->resource->fakeBuildOptions = ['hide' => ['name', 'updated_at']];
        $value = $this->resource->toArray(request()->create('x'));

        expect($value)
            ->toHaveKeys(['id', 'created_at'])
            ->not->toHaveKeys(['name', 'updated_at'])
            ->toMatchArray([
                'id' => 1,
                'created_at' => $this->model->created_at->toISOString(),
            ]);
    });

    it('appends additional values', function () {
        $this->resource->fakeBuildOptions = ['extra' => ['more' => 'values', 'test' => 123]];
        $value = $this->resource->toArray(request()->create('x'));

        expect($value)
            ->toHaveKeys(['more', 'test'])
            ->toMatchArray([
                'more' => 'values',
                'test' => 123,
            ]);
    });

    describe('extra option with per-value callables', function () {
        it('invokes callable extra value with resource and uses returned value', function () {
            $this->resource->fakeBuildOptions = [
                'extra' => ['computed' => fn ($resource) => $resource->name.'_computed'],
            ];
            $value = $this->resource->toArray(request()->create('x'));

            expect($value)
                ->toHaveKey('computed')
                ->computed->toBe('build_computed');
        });

        it('callable extra value receives correct resource instance', function () {
            $receivedResource = null;
            $this->resource->fakeBuildOptions = [
                'extra' => ['key' => function ($resource) use (&$receivedResource) {
                    $receivedResource = $resource;

                    return 'value';
                }],
            ];
            $this->resource->toArray(request()->create('x'));

            expect($receivedResource)->toBe($this->model);
        });

        it('handles mixed static and callable values in same extra array', function () {
            $this->resource->fakeBuildOptions = [
                'extra' => [
                    'static' => 'hello',
                    'computed' => fn ($r) => strtoupper($r->name),
                ],
            ];
            $value = $this->resource->toArray(request()->create('x'));

            expect($value)
                ->static->toBe('hello')
                ->computed->toBe('BUILD');
        });

        it('resolves multiple callable values independently', function () {
            $this->resource->fakeBuildOptions = [
                'extra' => [
                    'first' => fn ($r) => $r->name.'_first',
                    'second' => fn ($r) => $r->name.'_second',
                ],
            ];
            $value = $this->resource->toArray(request()->create('x'));

            expect($value)
                ->first->toBe('build_first')
                ->second->toBe('build_second');
        });
    });

    it('ignores loaded values if relationship is not loaded', function () {
        $this->model->items()->create(['name' => 'related']);
        $this->resource->fakeBuildOptions = ['relations' => ['items' => TestResource::class]];
        $value = $this->resource->toArray(request()->create('x'));

        expect($value)
            ->not->toHaveKey('items');
    });

    it('appends loaded relationship values as resources', function () {
        $this->model->items()->create(['name' => 'related']);
        $this->model->load('items');
        $this->resource->fakeBuildOptions = ['relations' => ['items' => RelatedResource::class]];
        $value = $this->resource->toArray(request()->create('x'));

        expect($value)
            ->toHaveKey('items');
        expect(json_decode(json_encode($value['items']), true))
            ->{0}->toBe([
                'id' => 2,
                'name' => 'related',
            ]);
    });

    it('appends loaded relationship as model array if not included in options', function () {
        $this->model->items()->create(['name' => 'related']);
        $this->model->load('items');
        $value = $this->resource->toArray(request()->create('x'));

        expect($value)
            ->toHaveKey('items')
            ->{'items'}->{0}->toMatchArray([
                'id' => 2,
                'parent_id' => 1,
            ]);
    });

    describe('callable relations option', function () {
        it('invokes callable relation value with resource and relation value', function () {
            $this->model->items()->create(['name' => 'related']);
            $this->model->load('items');
            $this->resource->fakeBuildOptions = [
                'relations' => [
                    'items' => fn ($resource, $relation) => [
                        'custom' => $resource->name.'_'.$relation->first()->name,
                    ],
                ],
            ];
            $value = $this->resource->toArray(request()->create('x'));

            expect($value)
                ->toHaveKey('items')
                ->items->toBe(['custom' => 'build_related']);
        });

        it('callable relation receives correct arguments', function () {
            $receivedArgs = [];
            $this->model->items()->create(['name' => 'related']);
            $this->model->load('items');
            $this->resource->fakeBuildOptions = [
                'relations' => [
                    'items' => function ($resource, $relation) use (&$receivedArgs) {
                        $receivedArgs = [$resource, $relation];

                        return ['verified' => true];
                    },
                ],
            ];
            $value = $this->resource->toArray(request()->create('x'));

            expect($receivedArgs[0])->toBe($this->model);
            expect($receivedArgs[1])->toBe($this->model->items);
            expect($value['items'])->toBe(['verified' => true]);
        });

        it('does not invoke callable when relation is not loaded', function () {
            $called = false;
            $this->model->items()->create(['name' => 'related']);
            $this->resource->fakeBuildOptions = [
                'relations' => [
                    'items' => function () use (&$called) {
                        $called = true;

                        return [];
                    },
                ],
            ];
            $value = $this->resource->toArray(request()->create('x'));

            expect($called)->toBe(false);
            expect($value)->not->toHaveKey('items');
        });
    });
});
