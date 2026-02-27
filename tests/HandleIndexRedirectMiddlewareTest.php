<?php

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use NLD\Momentum\Middleware\HandleIndexRedirect;
use NLD\Momentum\IndexStateSessionStore;
use NLD\Momentum\Tests\Support\TestController;

beforeEach(function () {
    TestController::macro('show', fn () => response('show-ok'));
    TestController::macro('store', fn () => response('store-ok'));

    Route::middleware([StartSession::class, HandleIndexRedirect::class])
        ->group(function () {
            Route::resource('redirect-tests', TestController::class);
            Route::get('/redirect-no-controller', fn () => response('closure-ok'));
        });
});

it('redirects back to index when go=back and current route has controller', function () {
    $store = new IndexStateSessionStore(TestController::class);
    $store->put('page', 3);

    $this->get('/redirect-tests/1?go=back')
        ->assertRedirect('redirect-tests?page=3');
});

it('passes request to next middleware when go is not back', function () {
    $this->get('/redirect-tests/1?go=forward')
        ->assertOk()
        ->assertSee('show-ok');
});

it('passes request to next middleware when go=back but route has no controller', function () {
    $this->get('/redirect-no-controller?go=back')
        ->assertOk()
        ->assertSee('closure-ok');
});

it('passes request to next middleware for non-safe methods', function () {
    $this->post('/redirect-tests?go=back')
        ->assertOk()
        ->assertSee('store-ok');
});
