<?php

declare(strict_types=1);

namespace NLD\Momentum\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware as InertiaMiddleware;
use NLD\Momentum\Contracts\MomentumController;

/**
 * Provides default shared Inertia props for Momentum requests.
 */
class HandleInertiaRequests extends InertiaMiddleware
{
    /**
     * Configure the root Inertia view from package config.
     */
    public function __construct()
    {
        $this->rootView = config('momentum.rootView', 'app');
    }

    /**
     * Merge framework, controller, and auth shared props.
     */
    public function share(Request $request): array
    {
        $controller = Route::current()?->getController();

        return array_merge(
            parent::share($request),
            $controller instanceof MomentumController ? $controller->getSharedData() : [],
            [
                'auth' => $this->shareAuth(),
            ]
        );
    }

    /**
     * Share authenticated user metadata and permissions.
     */
    public function shareAuth(): array
    {
        $user = Auth::user();

        return [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permissions' => $this->userPermissions($user),
            ] : null,
        ];
    }

    /**
     * Resolve permissions payload for the current user.
     */
    public function userPermissions(mixed $user = null): array
    {
        return [];
    }
}
