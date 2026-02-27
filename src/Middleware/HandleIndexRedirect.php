<?php

declare(strict_types=1);

namespace NLD\Momentum\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use NLD\Momentum\Contracts\MomentumController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles go=back style requests by redirecting to persisted index state.
 */
class HandleIndexRedirect
{
    /**
     * Redirect back-navigation requests for Momentum controllers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only auto-redirect index navigation on safe requests.
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $goParam = config('momentum.paramNames.go', 'go');
        $backValue = config('momentum.paramNames.back', 'back');

        if ($request->has($goParam) && $request->get($goParam) === $backValue) {
            $controller = Route::current()?->getController();

            if ($controller instanceof MomentumController) {
                return $controller->redirect();
            }
        }

        return $next($request);
    }
}
