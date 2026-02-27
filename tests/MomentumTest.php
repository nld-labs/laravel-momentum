<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as LaravelController;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use NLD\Momentum\Contracts\MomentumController;
use NLD\Momentum\IndexResponseBuilder;
use NLD\Momentum\Tests\Support\SecondController;
use NLD\Momentum\Tests\Support\TestController;
use NLD\Momentum\Tests\Support\TestModel;
use NLD\Momentum\Traits\Momentum;

beforeEach(function () {
    $this->controller = new TestController;
});

describe('indexResponse()', function () {
    it('returns IndexResponseBuilder instance', function () {
        $this->setCurrentControllerRoute(TestController::class);

        $index = $this->controller->indexResponse(TestModel::query());
        expect($index)->toBeInstanceOf(IndexResponseBuilder::class);
    });
});

describe('flash()', function () {
    it('returns controller instance', function () {
        $result = $this->controller->flash('success', 'Test message');

        expect($result)->toBe($this->controller);
    });

    it('adds flash data to the response', function () {
        $this->setCurrentControllerRoute($this->controller);

        $response = $this->controller
            ->flash('success', 'Operation successful')
            ->render('testComponent');

        expect($response)->toBeInstanceOf(\Inertia\Response::class);

        $this->inertiaResponse($response)
            ->assertInertia(fn (Assert $page) => $page
                ->component('testComponent')
                ->hasFlash('success', 'Operation successful')
            );
    });
});

describe('render()', function () {
    test('returns Inertia response with passed props', function () {
        $this->setCurrentControllerRoute($this->controller);

        $response = $this->controller->render('testComponent', ['testing' => 123]);

        expect($response)->toBeInstanceOf(\Inertia\Response::class);

        $this->inertiaResponse($response)
            ->assertInertia(fn (Assert $page) => $page
                ->component('testComponent')
                ->where('testing', 123)
            );
    });

    it('returns JsonResponse with props and shared data if request expects JSON', function () {
        $this->controller->setProperty('fakeSharedData', [
            'shared' => 'data',
            'more' => true,
        ]);

        request()->headers->set('Accept', 'application/json');

        $response = $this->controller->render('testComponent', ['testing' => 123]);
        $data = json_decode(json_encode($response->getData()), true);

        expect($response)
            ->toBeInstanceOf(JsonResponse::class);

        expect($data)->toBe([
            'shared' => 'data',
            'more' => true,
            'testing' => 123,
        ]);
    });
});

describe('getIndexRouteName()', function () {
    it('finds controller index route name', function () {
        Route::resource('nested.admin.users', TestController::class);

        expect($this->controller->getIndexRouteName())->toBe('nested.admin.users.index');
    });

    it('throws error if controller has no registered index route', function () {
        expect(function () {
            $this->controller->getIndexRouteName();
        })->toThrow(LogicException::class);
    });

    it('throws error if controller index route is unnamed', function () {
        Route::get('users', [TestController::class, 'index']);

        expect(function () {
            $this->controller->getIndexRouteName();
        })->toThrow(LogicException::class, 'Controller index route must be named.');
    });
});

describe('getIndexUrl()', function () {
    it('builds index URL using named params from controller and passed values', function () {
        $this->controller->setProperty('fakeIndexRouteParams', [
            'nested' => 'nest',
        ]);
        Route::resource('nested.admin.users', TestController::class);

        expect($this->controller->getIndexUrl(['admin' => 1, 'param' => 'test']))
            ->toBe('http://localhost/nested/nest/admin/1/users?param=test');
    });

    it('builds index URL using available unnamed params if no named values are given', function () {
        $this->controller->setProperty('fakeIndexRouteParams', [
            'first', 'second',
        ]);
        Route::resource('nested.admin.users', TestController::class);

        expect($this->controller->getIndexUrl(['admin' => 1, 'param' => 'test']))
            ->toBe('http://localhost/nested/first/admin/1/users?param=test&second');
    });
});

describe('getSharedData()', function () {
    it('returns empty shared data', function () {
        $this->setCurrentControllerRoute(TestController::class);

        $controller = new class extends LaravelController implements MomentumController
        {
            use Momentum;
        };

        expect($controller->getSharedData())->toBeEmpty();
    });

    it('uses shared data from controller based on current action', function ($action, $data, $formData = []) {
        $this->setCurrentControllerRoute(TestController::class, $action);

        $this->controller->setProperty('fakeSharedData', $data);
        if (! empty($formData)) {
            $this->controller->setProperty('fakeSharedFormData', $formData);
        }

        expect($this->controller->getSharedData())->toBe(array_merge($data, $formData));
    })->with([
        ['index', ['all' => 'methods']],
        ['create', ['all' => 'methods'], ['only' => 'form actions']],
        ['edit', ['all' => 'methods'], ['only' => 'form actions']],
    ]);
});

describe('getRedirectTarget()', function () {
    it('returns configured redirect target', function () {
        $this->controller->setProperty('fakeRedirectTargets', [
            'to-second' => ['controller' => TestController::class],
        ]);

        expect($this->controller->getRedirectTarget('to-second'))
            ->toBe(['controller' => TestController::class]);
    });

    it('returns null when controller does not define redirectTargets', function () {
        $controller = new class extends LaravelController implements MomentumController
        {
            use Momentum;
        };

        expect($controller->getRedirectTarget('unknown'))
            ->toBeNull();
    });
});

describe('getSearchScopeName()', function () {
    it('returns default search scope', function () {
        expect($this->controller->getSearchScopeName())->toBe('search');
    });

    it('uses search scope from controller', function () {
        $this->controller->setProperty('searchScope', 'fakeSearch');

        expect($this->controller->getSearchScopeName())->toBe('fakeSearch');
    });
});

describe('session-backed params', function () {
    it('returns all persisted params via getPersistedParams', function () {
        $this->controller->persistIndexParam('first', 'one');
        $this->controller->persistIndexParam('second', 2);

        expect($this->controller->getPersistedParams())->toBe([
            'first' => 'one',
            'second' => 2,
        ]);
    });

    it('forgets persisted param via forgetPersistedParam', function () {
        $this->controller->persistIndexParam('first', 'one');
        $this->controller->forgetPersistedParam('first');

        expect($this->controller->getPersistedParam('first'))->toBeNull();
    });

    it('persists index params from request via persistIndexParams', function () {
        $this->controller->setProperty('indexConfig', [
            'searchable' => true,
            'sortable' => 'name,email',
            'params' => 'status',
        ]);

        request()->merge([
            'search' => 'term',
            'sort' => 'email!desc',
            'status' => 'active',
            'ignored' => 'x',
        ]);

        $this->controller->persistIndexParams();

        expect($this->controller->getPersistedParams())->toBe([
            'search' => 'term',
            'sort' => 'email!desc',
            'status' => 'active',
        ]);
    });
});

describe('buildIndexQuery()', function () {
    it('returns query builder', function () {
        $query = $this->controller->buildIndexQuery(TestModel::query());

        expect($query)->toBeInstanceOf(Builder::class);
    });

    it('uses given query', function () {
        $query = $this->controller->buildIndexQuery(TestModel::where('id', '>', 10));

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models" where "id" > ?')
            ->getBindings()
            ->toBe([10]);
    });

    it('uses empty query when given class name', function () {
        $query = $this->controller->buildIndexQuery(TestModel::class);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models"');
    });

    it('uses values from indexConfig', function () {
        $this->controller->setProperty('indexConfig', [
            'searchable' => true,
            'sortable' => ['name', 'email'],
        ]);

        request()->merge([
            'sort' => 'email!asc,name!desc',
            'search' => 'test',
        ]);
        $query = $this->controller->buildIndexQuery(TestModel::class);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models" where "name" = ? order by "email" asc, "name" desc')
            ->getBindings()->toBe(['test']);
    });

    it('does not apply sorting if none requested and no defaultSort exists', function () {
        $this->controller->setProperty('indexConfig', [
            'searchable' => false,
            'sortable' => ['name', 'other'],
        ]);

        $query = $this->controller->buildIndexQuery(TestModel::class);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models"');
    });

    it('uses model sorting method if available', function () {
        $this->controller->setProperty('indexConfig', [
            'searchable' => false,
            'sortable' => ['some_method'],
        ]);
        request()->merge(['sort' => 'some_method!desc']);

        $query = $this->controller->buildIndexQuery(TestModel::class);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models" order by "model_method" desc');
    });

    it('ignores search string from request if not searchable', function () {
        $this->controller->setProperty('indexConfig', [
            'searchable' => false,
        ]);
        request()->merge(['search' => 'something']);

        $query = $this->controller->buildIndexQuery(TestModel::class);

        expect($query)
            ->toSql()->toBe('select * from "test_models"');
    });

    it('uses search scope set by controller property', function () {
        $this->controller->setProperty('indexConfig', [
            'searchable' => true,
        ]);
        $this->controller->setProperty('searchScope', 'customScope');
        request()->merge(['search' => 'something']);

        $query = $this->controller->buildIndexQuery(TestModel::class);

        expect($query)
            ->toSql()->toBe('select * from "test_models" where "search" = ?')
            ->getBindings()->toBe(['something']);
    });
});

describe('redirect()', function () {
    beforeEach(function () {
        Route::resource('tests', TestController::class);
    });

    it('correctly redirects to redirect target', function ($requestTarget, $page, $redirectUrl) {
        Route::resource('second', SecondController::class);

        $secondController = new SecondController;
        $secondController->persistIndexParam('page', 7);
        app()->instance(SecondController::class, $secondController);

        TestController::macro('show', function () use ($requestTarget, $page) {
            $this->fakeRedirectTargets = [
                'string' => 'some.url',
                'second' => ['controller' => SecondController::class],
            ];
            request()->merge(['redirect' => $requestTarget]);
            $this->persistIndexParam('page', 2);

            return $this->redirect($page, ['param' => 'test']);
        });

        $response = $this->get('/tests/redirect');
        $response->assertRedirect($redirectUrl);
    })->with([
        'without request target' => [
            null,
            5,
            'tests?page=5&param=test',
        ],
        'without request target with page=1' => [
            null,
            1,
            'tests?param=test',
        ],
        'without request target and without page' => [
            null,
            null,
            'tests?page=2&param=test',
        ],
        'unknown request target' => [
            'unknown',
            5,
            'tests?page=5&param=test',
        ],
        'string request target' => [
            'string',
            3,
            'some.url',
        ],
        'controller request target' => [
            'second',
            3,
            'second?page=7&param=test',
        ],
    ]);

    it('does not follow redirect param when redirecting to another controller', function () {
        Route::resource('second', SecondController::class);

        $secondController = new SecondController;
        $secondController->persistIndexParam('page', 3);
        $secondController->setProperty('fakeRedirectTargets', [
            'should-not-follow' => 'http://evil.example.com',
        ]);
        app()->instance(SecondController::class, $secondController);

        TestController::macro('show', function () {
            $this->fakeRedirectTargets = [
                'second' => ['controller' => SecondController::class],
            ];
            request()->merge(['redirect' => 'second']);

            return $this->redirect(null, ['param' => 'test']);
        });

        $response = $this->get('/tests/redirect');
        // Should redirect to second controller's persisted state, NOT follow the 'should-not-follow' target
        $response->assertRedirect('second?page=3&param=test');
    });

    it('includes persisted per page value in redirects', function () {
        $this->controller->persistIndexParam('page', 2);
        $this->controller->persistIndexParam('perPage', 50);

        TestController::macro('show', function () {
            return $this->redirect();
        });

        $response = $this->get('/tests/redirect');

        expect($response->headers->get('Location'))
            ->toContain('/tests?')
            ->toContain('page=2')
            ->toContain('perPage=50');
    });
});
