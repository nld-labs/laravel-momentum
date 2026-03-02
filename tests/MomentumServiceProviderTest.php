<?php

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use NLD\Momentum\MomentumServiceProvider;
use NLD\Momentum\Tests\Support\TestModel;
use NLD\Momentum\Tests\Support\TestResource;

beforeEach(function () {
    $this->app->register(MomentumServiceProvider::class);
});

afterEach(function () {
    JsonResource::wrap('data');
});

it('registers artisan command', function () {
    expect(Artisan::all())->toHaveKey('momentum:middleware');
});

it('omits data wrapper when resource_wrapping is true', function () {
    Config::set('momentum.resource_wrapping', true);

    $model = new TestModel(['id' => 1, 'name' => 'Test']);
    $resource = new TestResource($model);
    $response = $resource->toResponse(request());

    expect($response->getData(true))->not->toHaveKey('data');
});

it('includes data wrapper when resource_wrapping is false', function () {
    JsonResource::wrap('data');

    $model = new TestModel(['id' => 1, 'name' => 'Test']);
    $resource = new TestResource($model);
    $response = $resource->toResponse(request());

    expect($response->getData(true))->toHaveKey('data');
});

it('service provider does not call withoutWrapping when resource_wrapping is false', function () {
    JsonResource::wrap('data');

    $model = new TestModel(['id' => 1, 'name' => 'Test']);
    $resource = new TestResource($model);
    $response = $resource->toResponse(request());

    expect($response->getData(true))->toHaveKey('data');
});
