<?php

declare(strict_types=1);

namespace NLD\Momentum\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use NLD\Momentum\Contracts\MomentumController;
use NLD\Momentum\IndexQueryBuilder;
use NLD\Momentum\IndexRequestState;
use NLD\Momentum\IndexResponseBuilder;
use NLD\Momentum\IndexStateSessionStore;
use UnitEnum;

/**
 * Provides reusable Momentum behavior for Inertia-enabled controllers.
 */
trait Momentum
{
    protected ?IndexRequestState $momentumIndexState = null;

    protected ?IndexStateSessionStore $momentumSessionStore = null;

    /**
     * Create an index response builder for the given query source.
     */
    public function indexResponse(string|Builder|Relation $query): IndexResponseBuilder
    {
        return new IndexResponseBuilder($this, $query);
    }

    /**
     * Render JSON for API calls or Inertia responses for browser requests.
     */
    public function render(string $component, array $props = []): Response|JsonResponse
    {
        if (request()->expectsJson()) {
            return response()->json([
                ...$this->getSharedData(),
                ...$props,
            ]);
        }

        return Inertia::render($component, $props);
    }

    /**
     * Flash values for use in the next Inertia response.
     */
    public function flash(BackedEnum|UnitEnum|string|array $key, mixed $value = null): static
    {
        Inertia::flash($key, $value);

        return $this;
    }

    /**
     * Redirect to index state or a configured redirect target.
     */
    public function redirect(?int $page = null, array $params = []): mixed
    {
        $redirectKey = request()->get('redirect');

        if ($redirectKey !== null && $redirectKey !== '') {
            $redirectTarget = $this->getRedirectTarget($redirectKey);

            if ($redirectTarget) {
                if (is_string($redirectTarget)) {
                    return redirect($redirectTarget);
                }

                if ($targetController = $redirectTarget['controller'] ?? null) {
                    $controller = is_string($targetController)
                        ? app($targetController)
                        : $targetController;

                    if (! $controller instanceof MomentumController) {
                        throw new \InvalidArgumentException(
                            'Redirect target controller must implement {MomentumController::class}.'
                        );
                    }

                    // No deep redirects — always redirect to target's persisted index state
                    return $this->buildSimpleRedirectFor($controller, null, $params);
                }
            }
        }

        return $this->buildSimpleRedirectFor($this, $page, $params);
    }

    /**
     * Build a redirect URL for a controller using persisted index params.
     */
    private function buildSimpleRedirectFor(MomentumController $controller, ?int $page, array $params): mixed
    {
        if ($page === null) {
            $page = (int) $controller->getPersistedParam('page');
        }

        if ($page < 2) {
            $page = null;
        }

        return redirect($controller->getIndexUrl([
            ...$controller->getPersistedParams(),
            'page' => $page,
            ...$params,
        ]));
    }

    /**
     * Resolve shared controller props, including form-specific data.
     */
    public function getSharedData(): array
    {
        $data = [];

        if (method_exists($this, 'sharedData')) {
            $data = $this->sharedData();
        }

        $method = Route::current()?->getActionMethod();
        if (($method === 'create' || $method === 'edit')
            && method_exists($this, 'sharedFormData')
        ) {
            $data = array_merge($data, $this->sharedFormData());
        }

        return $data;
    }

    /**
     * Resolve a redirect target from optional controller mappings.
     */
    public function getRedirectTarget(string $redirect): mixed
    {
        $targets = [];

        if (method_exists($this, 'redirectTargets')) {
            $targets = $this->redirectTargets();
        }

        return $targets[$redirect] ?? null;
    }

    /**
     * Build an index query using the current resolved request state.
     */
    public function buildIndexQuery(Builder|Relation|string $query): Builder
    {
        $state = $this->resolveIndexState();
        $builder = new IndexQueryBuilder($this->getSearchScopeName());

        return $builder->build($query, $state);
    }

    /**
     * Resolve and return the current index request state.
     */
    public function resolveIndexState(): array
    {
        return $this->getMomentumIndexState()->resolve();
    }

    /**
     * Resolve the named route for the controller index action.
     */
    public function getIndexRouteName(): string
    {
        $route = Route::getRoutes()
            ->getByAction(get_class($this).'@index');

        if (! $route) {
            throw new \LogicException('Controller index route is not registered.');
        }

        $routeName = $route->getName();

        if (! is_string($routeName) || $routeName === '') {
            throw new \LogicException('Controller index route must be named.');
        }

        return $routeName;
    }

    /**
     * Generate the index URL with optional route parameters.
     */
    public function getIndexUrl(array $params = []): string
    {
        if (method_exists($this, 'indexRouteParams')) {
            $params = array_merge(
                (array) $this->indexRouteParams(),
                $params,
            );
        }

        return route($this->getIndexRouteName(), $params);
    }

    /**
     * Resolve the configured model scope used for search.
     */
    public function getSearchScopeName(): string
    {
        if (property_exists($this, 'searchScope')) {
            return $this->searchScope;
        }

        return config('momentum.searchScope', 'search');
    }

    /**
     * Get a persisted index parameter.
     */
    public function getPersistedParam(string $key, mixed $default = null): mixed
    {
        return $this->getMomentumSessionStore()->get($key, $default);
    }

    /**
     * Get all persisted index parameters.
     */
    public function getPersistedParams(): array
    {
        return $this->getMomentumSessionStore()->all();
    }

    /**
     * Persist all configured index parameters from current request.
     */
    public function persistIndexParams(): void
    {
        $this->getMomentumSessionStore()->persistFromRequest($this->getMomentumIndexState());
    }

    /**
     * Persist a single index parameter.
     */
    public function persistIndexParam(string $key, mixed $value): void
    {
        $this->getMomentumSessionStore()->put($key, $value);
    }

    /**
     * Forget a persisted index parameter.
     */
    public function forgetPersistedParam(string $key): void
    {
        $this->getMomentumSessionStore()->forget($key);
    }

    /**
     * Get index parameter names eligible for persistence.
     */
    public function getPersistedParamNames(): array
    {
        return $this->getMomentumIndexState()->getPersistedParamNames();
    }

    /**
     * Lazily create and cache the index request state object.
     */
    protected function getMomentumIndexState(): IndexRequestState
    {
        if ($this->momentumIndexState === null) {
            $this->momentumIndexState = IndexRequestState::fromController($this, request());
        }

        return $this->momentumIndexState;
    }

    /**
     * Lazily create and cache the session-backed state store.
     */
    protected function getMomentumSessionStore(): IndexStateSessionStore
    {
        if ($this->momentumSessionStore === null) {
            $this->momentumSessionStore = new IndexStateSessionStore(get_class($this));
        }

        return $this->momentumSessionStore;
    }
}
