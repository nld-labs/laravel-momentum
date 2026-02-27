<?php

namespace NLD\Momentum\Tests\Support;

use Illuminate\Routing\Controller as LaravelController;
use Inertia\Inertia;
use Inertia\Response;
use NLD\Momentum\Contracts\MomentumController;
use NLD\Momentum\Traits\Momentum;

class InertiaTestController extends LaravelController implements MomentumController
{
    use Momentum;

    public function index(): Response
    {
        return Inertia::render('test', ['param' => 222]);
    }

    public function sharedData(): array
    {
        return ['shared' => 'test-data'];
    }
}
