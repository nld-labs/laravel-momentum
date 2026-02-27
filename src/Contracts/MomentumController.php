<?php

declare(strict_types=1);

namespace NLD\Momentum\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Inertia\Response;
use NLD\Momentum\IndexResponseBuilder;

/**
 * Defines the controller contract required by the Momentum helpers.
 */
interface MomentumController
{
    /**
     * Create an index response builder for a given query source.
     */
    public function indexResponse(string|Builder|Relation $query): IndexResponseBuilder;

    /**
     * Render an Inertia response or JSON payload.
     */
    public function render(string $component, array $props = []): Response|JsonResponse;

    /**
     * Flash data into the next Inertia response.
     */
    public function flash(\BackedEnum|\UnitEnum|string|array $key, mixed $value = null): static;

    /**
     * Redirect to the index route with persisted state.
     */
    public function redirect(?int $page = null, array $params = []): mixed;

    /**
     * Resolve shared data for current action.
     */
    public function getSharedData(): array;

    /**
     * Resolve a named redirect target definition.
     */
    public function getRedirectTarget(string $redirect): mixed;

    /**
     * Get a persisted index parameter from session.
     */
    public function getPersistedParam(string $key, mixed $default = null): mixed;

    /**
     * Get all persisted index parameters from session.
     */
    public function getPersistedParams(): array;

    /**
     * Persist current request index parameters into session.
     */
    public function persistIndexParams(): void;

    /**
     * Persist a single index parameter into session.
     */
    public function persistIndexParam(string $key, mixed $value): void;

    /**
     * Remove a persisted index parameter from session.
     */
    public function forgetPersistedParam(string $key): void;

    /**
     * List index parameter names that can be persisted.
     */
    public function getPersistedParamNames(): array;

    /**
     * Resolve the named route used for index actions.
     */
    public function getIndexRouteName(): string;

    /**
     * Build the index route URL with provided parameters.
     */
    public function getIndexUrl(array $params = []): string;

    /**
     * Build the index query by applying resolved state.
     */
    public function buildIndexQuery(Builder|Relation|string $query): Builder;

    /**
     * Resolve normalized index state from request and config.
     */
    public function resolveIndexState(): array;

    /**
     * Resolve the model search scope name.
     */
    public function getSearchScopeName(): string;
}
