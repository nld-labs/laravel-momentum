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

describe('formatData()', function () {
    beforeEach(function () {
        $this->model = TestModel::create(['name' => 'build']);
        $this->resource = TestResource::make($this->model);
    });

    it('has only allowed keys', function () {
        $this->resource->fakeBuildOptions = ['only' => ['id', 'name']];
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
        $this->resource->fakeBuildOptions = ['except' => ['name', 'updated_at']];
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
        $this->resource->fakeBuildOptions = ['append' => ['more' => 'values', 'test' => 123]];
        $value = $this->resource->toArray(request()->create('x'));

        expect($value)
            ->toHaveKeys(['more', 'test'])
            ->toMatchArray([
                'more' => 'values',
                'test' => 123,
            ]);
    });

    it('ignores loaded values if relationship is not loaded', function () {
        $this->model->items()->create(['name' => 'related']);
        $this->resource->fakeBuildOptions = ['loaded' => ['items' => TestResource::class]];
        $value = $this->resource->toArray(request()->create('x'));

        expect($value)
            ->not->toHaveKey('items');
    });

    it('appends loaded relationship values as resources', function () {
        $this->model->items()->create(['name' => 'related']);
        $this->model->load('items');
        $this->resource->fakeBuildOptions = ['loaded' => ['items' => RelatedResource::class]];
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
});
