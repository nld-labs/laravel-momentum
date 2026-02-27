<?php

namespace NLD\Momentum\Tests\Support;

use NLD\Momentum\Middleware\HandleInertiaRequests;

class PermissionsMiddleware extends HandleInertiaRequests
{
    public function userPermissions(mixed $user = null): array
    {
        return [$user->name.'-permissions'];
    }
}
