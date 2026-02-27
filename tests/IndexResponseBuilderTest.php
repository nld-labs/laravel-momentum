<?php

use Illuminate\Support\Facades\Route;
use Inertia\Response;
use Inertia\Testing\AssertableInertia as Assert;
use NLD\Momentum\IndexResponseBuilder;
use NLD\Momentum\Tests\Support\CustomTransformer;
use NLD\Momentum\Tests\Support\JsonResourceTransformer;
use NLD\Momentum\Tests\Support\LaravelDataTransformer;
use NLD\Momentum\Tests\Support\TestController;
use NLD\Momentum\Tests\Support\TestModel;

beforeEach(function () {
    $this->query = TestModel::query();
    $this->controller = new TestController;
    $this->index = new IndexResponseBuilder($this->controller, $this->query);

    Route::resource('tests', TestController::class);
});

test('paginate() returns IndexResponseBuilder instance', function () {
    $index = $this->index->paginate();

    expect($index)->toBe($this->index);
});

test('withPerPageQuery() returns IndexResponseBuilder instance', function () {
    $index = $this->index->withPerPageQuery();

    expect($index)->toBe($this->index);
});

test('transform() returns IndexResponseBuilder instance', function () {
    $index = $this->index->transform(null);

    expect($index)->toBe($this->index);
});

describe('render()', function () {
    test('returns Inertia response with given component and props', function () {
        TestModel::create(['name' => 'first']);
        $response = $this->index->render('component', ['testing' => 123]);

        expect($response)->toBeInstanceOf(Response::class);

        $this->inertiaResponse($response)
            ->assertInertia(fn (Assert $page) => $page
                ->component('component')
                ->where('testing', 123)
                ->where('data.0.name', 'first')
            );
    });

    test('returns paginated data with default per page value', function () {
        TestModel::create(['name' => 'first']);
        $response = $this->index->paginate()->render('test');

        $this->inertiaResponse($response)
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.pagination.total', 1)
                ->where('meta.pagination.perPage', 15)
                ->where('meta.pagination.currentPage', 1)
                ->where('meta.pagination.totalPages', 1)
                ->where('data.0.name', 'first')
            );
    });

    test('returns paginated data per page value set by paginate()', function () {
        TestModel::create(['name' => 'first']);
        TestModel::create(['name' => 'second']);
        request()->merge(['page' => 2]);

        $response = $this->index->paginate(1)->render('test');

        $this->inertiaResponse($response)
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.pagination.total', 2)
                ->where('meta.pagination.perPage', 1)
                ->where('meta.pagination.currentPage', 2)
                ->where('meta.pagination.totalPages', 2)
                ->where('data.0.name', 'second')
            );
    });

    test('uses per page query value when enabled', function () {
        TestModel::create(['name' => 'first']);
        TestModel::create(['name' => 'second']);
        request()->merge(['page' => 2, 'perPage' => 1]);

        $response = $this->index
            ->paginate()
            ->withPerPageQuery()
            ->render('test');

        $this->inertiaResponse($response)
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.pagination.total', 2)
                ->where('meta.pagination.perPage', 1)
                ->where('meta.pagination.currentPage', 2)
                ->where('meta.pagination.totalPages', 2)
                ->where('data.0.name', 'second')
            );
    });

    test('uses custom per page query name when enabled', function () {
        TestModel::create(['name' => 'first']);
        TestModel::create(['name' => 'second']);
        request()->merge(['page' => 2, 'size' => 1]);

        $response = $this->index
            ->paginate()
            ->withPerPageQuery('size')
            ->render('test');

        $this->inertiaResponse($response)
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.pagination.total', 2)
                ->where('meta.pagination.perPage', 1)
                ->where('meta.pagination.currentPage', 2)
                ->where('meta.pagination.totalPages', 2)
                ->where('data.0.name', 'second')
            );
    });

    test('uses configured per page query name when enabled', function () {
        config()->set('momentum.paramNames.perPage', 'size');

        TestModel::create(['name' => 'first']);
        TestModel::create(['name' => 'second']);
        request()->merge(['page' => 2, 'size' => 1]);

        $response = $this->index
            ->paginate()
            ->withPerPageQuery()
            ->render('test');

        $this->inertiaResponse($response)
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.pagination.total', 2)
                ->where('meta.pagination.perPage', 1)
                ->where('meta.pagination.currentPage', 2)
                ->where('meta.pagination.totalPages', 2)
                ->where('data.0.name', 'second')
            );
    });

    test('falls back to paginate per page when query value is invalid', function () {
        TestModel::create(['name' => 'first']);
        TestModel::create(['name' => 'second']);
        TestModel::create(['name' => 'third']);
        request()->merge(['perPage' => 'invalid']);

        $response = $this->index
            ->paginate(2)
            ->withPerPageQuery()
            ->render('test');

        $this->inertiaResponse($response)
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.pagination.total', 3)
                ->where('meta.pagination.perPage', 2)
                ->where('meta.pagination.currentPage', 1)
                ->where('meta.pagination.totalPages', 2)
            );
    });

    test('uses persisted per page when query value is missing', function () {
        TestModel::create(['name' => 'first']);
        TestModel::create(['name' => 'second']);
        $this->controller->persistIndexParam('perPage', 1);
        request()->merge(['page' => 2]);

        $response = $this->index
            ->paginate()
            ->withPerPageQuery()
            ->render('test');

        $this->inertiaResponse($response)
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.pagination.total', 2)
                ->where('meta.pagination.perPage', 1)
                ->where('meta.pagination.currentPage', 2)
                ->where('meta.pagination.totalPages', 2)
                ->where('data.0.name', 'second')
            );
    });

    test('redirects to the last page if requested page does not exist', function () {
        TestModel::create(['name' => 'first']);
        TestModel::create(['name' => 'second']);

        $index = $this->index;
        TestController::macro('index', function () use ($index) {
            return $index->paginate(1)->render('testComponent');
        });

        $response = $this->get('/tests?page=5');

        $response->assertRedirect('tests?page=2');
    });

    test('keeps per page query value on redirect when requested page does not exist', function () {
        TestModel::create(['name' => 'first']);
        TestModel::create(['name' => 'second']);

        $index = $this->index;
        TestController::macro('index', function () use ($index) {
            return $index->paginate()->withPerPageQuery()->render('testComponent');
        });

        $response = $this->get('/tests?page=5&perPage=1');

        $response->assertRedirect('tests?perPage=1&page=2');
    });

    test('applies transformer', function ($page, $transformer) {
        $model1 = TestModel::create(['name' => 'first']);
        $model2 = TestModel::create(['name' => 'second']);

        $index = $this->index;

        if ($page) {
            request()->merge(['page' => $page]);

            $index->paginate(1);
        }

        if ($transformer) {
            $index->transform($transformer);
        }

        $props = $this->inertiaResponse($index->render('Testing'))->inertiaProps();
        $items = $props['data']['data'] ?? $props['data'];

        $data = $transformer ? [[
            'id' => $model1->id,
            'name' => $model1->name,
            'transformed' => true,
        ], [
            'id' => $model2->id,
            'name' => $model2->name,
            'transformed' => true,
        ]] : [
            array_merge($model1->toArray(), ['parent_id' => null]),
            array_merge($model2->toArray(), ['parent_id' => null]),
        ];

        if ($page) {
            expect($items)
                ->toMatchArray([
                    $data[$page - 1],
                ])
                ->toHaveCount(1);

            expect($props['meta']['pagination'])
                ->toMatchArray([
                    'currentPage' => $page,
                    'total' => 2,
                    'perPage' => 1,
                    'totalPages' => 2,
                ]);

        } else {
            expect($items)
                ->toHaveCount(2)
                ->toMatchArray($data);

            expect($props['meta']['pagination'])->toBeNull();
        }

    })->with([
        null,
        1,
        2,
    ])->with([
        null,
        JsonResourceTransformer::class,
        ...class_exists(\Spatie\LaravelData\Data::class) ? [LaravelDataTransformer::class] : [],
        CustomTransformer::class,
        function ($items) {
            return collect($items)->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'transformed' => true,
            ]);
        },
    ]);

    test('redirects without page number if only one page exists', function () {
        TestModel::create(['name' => 'first']);
        TestModel::create(['name' => 'second']);

        $index = $this->index;
        TestController::macro('index', function () use ($index) {
            return $index->paginate(2)->render('testComponent');
        });

        $response = $this->get('/tests?page=5');

        $response->assertRedirect('tests');
    });

    test('throws InvalidArgumentException for missing transformer class', function () {
        TestModel::create(['name' => 'first']);

        expect(fn () => $this->index->transform('MissingTransformerClass')->render('test'))
            ->toThrow(InvalidArgumentException::class, 'Transformer class does not exist');
    });

    test('throws InvalidArgumentException for unsupported transformer class', function () {
        TestModel::create(['name' => 'first']);

        expect(fn () => $this->index->transform(stdClass::class)->render('test'))
            ->toThrow(InvalidArgumentException::class, 'Transformer must be a JsonResource');
    });

    test('saves current page number and other params', function () {
        TestModel::create(['name' => 'first']);
        TestModel::create(['name' => 'second']);
        TestModel::create(['name' => 'first']);

        $this->controller->setProperty('indexConfig', [
            'searchable' => true,
            'sortable' => 'name',
            'params' => 'prop1',
        ]);

        request()->merge([
            'page' => 2,
            'search' => 'first',
            'sort' => 'name!asc',
            'prop1' => 11,
            'prop2' => 22,
        ]);

        $this->index->paginate(1)->render('test');

        expect($this->controller)
            ->getPersistedParam('page')->toBe(2)
            ->getPersistedParam('search')->toBe('first')
            ->getPersistedParam('sort')->toBe('name!asc')
            ->getPersistedParam('prop1')->toBe(11)
            ->getPersistedParam('prop2')->toBeNull();
    });
});
