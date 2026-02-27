<?php

use Illuminate\Support\Facades\Artisan;
use NLD\Momentum\MomentumServiceProvider;

beforeEach(function () {
    $this->app->register(MomentumServiceProvider::class);
});

it('registers artisan command', function () {
    expect(Artisan::all())->toHaveKey('momentum:middleware');
});
