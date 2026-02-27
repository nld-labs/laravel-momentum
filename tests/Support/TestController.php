<?php

namespace NLD\Momentum\Tests\Support;

use Illuminate\Routing\Controller as LaravelController;
use Illuminate\Support\Traits\Macroable;
use NLD\Momentum\Contracts\MomentumController;
use NLD\Momentum\Traits\Momentum;

class TestController extends LaravelController implements MomentumController
{
    use Macroable, Momentum {
        Macroable::__call as macroCall;
    }

    protected $fakeIndexRouteParams = [];

    protected $fakeSharedData = [];

    protected $fakeSharedFormData = [];

    protected $fakeRedirectTargets = [];

    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        return parent::__call($method, $parameters);
    }

    public function indexRouteParams(): array
    {
        return $this->fakeIndexRouteParams;
    }

    public function sharedData(): array
    {
        return $this->fakeSharedData;
    }

    public function sharedFormData(): array
    {
        return $this->fakeSharedFormData;
    }

    public function redirectTargets(): array
    {
        return $this->fakeRedirectTargets;
    }

    public function setProperty(string $name, mixed $value): void
    {
        $this->{$name} = $value;
    }
}
