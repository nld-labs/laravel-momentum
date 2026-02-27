<?php

namespace NLD\Momentum\Tests\Support;

use NLD\Momentum\Middleware\HandleInertiaRequests;

class CustomAuthMiddleware extends HandleInertiaRequests
{
    public function shareAuth(): array
    {
        return ['shared-auth-test'];
    }
}
