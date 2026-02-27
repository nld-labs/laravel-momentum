<?php

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use NLD\Momentum\Middleware\HandleInertiaRequests as Middleware;
use NLD\Momentum\Tests\Support\CustomAuthMiddleware;
use NLD\Momentum\Tests\Support\InertiaTestController;
use NLD\Momentum\Tests\Support\PermissionsMiddleware;
use NLD\Momentum\Tests\Support\TestUser;

beforeEach(function () {
    $stack = [StartSession::class, Middleware::class];

    Route::middleware($stack)
        ->get('/middleware/controller', [InertiaTestController::class, 'index']);

    Route::middleware($stack)
        ->get('/middleware/closure', fn () => Inertia::render('test', ['param' => 222]));

    Route::middleware([StartSession::class, CustomAuthMiddleware::class])
        ->get('/middleware/custom-auth', [InertiaTestController::class, 'index']);

    Route::middleware([StartSession::class, PermissionsMiddleware::class])
        ->get('/middleware/permissions', [InertiaTestController::class, 'index']);
});

it('extends Inertia\Middleware', function () {
    $middleware = new Middleware;

    expect($middleware)->toBeInstanceOf(\Inertia\Middleware::class);
});

it('shares data from controller context', function () {
    $this->get('/middleware/controller')
        ->assertInertia(fn (Assert $page) => $page
            ->component('test')
            ->where('shared', 'test-data')
            ->where('param', 222)
        );
});

it('does not fail if there is no current controller', function () {
    $this->get('/middleware/closure')
        ->assertInertia(fn (Assert $page) => $page
            ->component('test')
            ->where('param', 222)
            ->missing('shared')
        );
});

it('shares auth data returned by shareAuth()', function () {
    $this->get('/middleware/custom-auth')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth', ['shared-auth-test'])
        );
});

it('shares user as null if not authenticated', function () {
    $this->get('/middleware/controller')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
        );
});

it('shares authenticated user data including permissions from userPermissions() method', function () {
    $user = TestUser::create([
        'name' => 'testing123',
        'email' => 'test@test.com',
    ]);

    $this->actingAs($user)
        ->get('/middleware/permissions')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', 'testing123')
            ->where('auth.user.email', 'test@test.com')
            ->where('auth.user.permissions', ['testing123-permissions'])
        );
});
