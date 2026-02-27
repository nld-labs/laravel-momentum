<?php

namespace NLD\Momentum\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Inertia\Response as InertiaResponse;
use Inertia\ServiceProvider as InertiaServiceProvider;
use NLD\Momentum\MomentumServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

abstract class TestCase extends TestbenchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.key', Str::random(32));
        Config::set('inertia.testing.ensure_pages_exist', false);
        View::addLocation(__DIR__.'/Support');

        $this->setupDatabase($this->app);
    }

    protected function inertiaResponse(InertiaResponse $response): TestResponse
    {
        $request = request()->create('/test');
        $request->setLaravelSession($this->app['session.store']);

        return TestResponse::fromBaseResponse($response->toResponse($request));
    }

    protected function setCurrentControllerRoute(mixed $controller, string $action = 'index'): void
    {
        $controllerClass = is_string($controller) ? $controller : $controller::class;

        if (! is_string($controller)) {
            $this->app->instance($controllerClass, $controller);
        }

        if (
            ! method_exists($controllerClass, $action)
            && method_exists($controllerClass, 'hasMacro')
            && method_exists($controllerClass, 'macro')
            && ! $controllerClass::hasMacro($action)
        ) {
            $controllerClass::macro($action, fn () => 'ok');
        }

        $uri = '/__test/current/'.md5($controllerClass.'@'.$action.microtime(true));
        Route::get($uri, [$controllerClass, $action]);
        $this->get($uri);
    }

    protected function setupDatabase($app): void
    {
        $builder = $app['db']->connection()->getSchemaBuilder();

        $builder->create('test_models', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('name');
            $table->integer('parent_id')->nullable();
        });

        $builder->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            InertiaServiceProvider::class,
            MomentumServiceProvider::class,
        ];

        if (class_exists(\Spatie\LaravelData\LaravelDataServiceProvider::class)) {
            $providers[] = \Spatie\LaravelData\LaravelDataServiceProvider::class;
        }

        return $providers;
    }
}
