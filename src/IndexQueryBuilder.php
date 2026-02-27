<?php

declare(strict_types=1);

namespace NLD\Momentum;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * Applies index state (sorting and search) to Eloquent queries.
 */
class IndexQueryBuilder
{
    /**
     * Create a query builder with a configurable search scope name.
     */
    public function __construct(
        protected string $searchScopeName = 'search'
    ) {}

    /**
     * Build the final query from the provided source and state.
     */
    public function build(Builder|Relation|string $query, array $state): Builder
    {
        $query = $this->resolveQuery($query);
        $query = $this->applySorting($query, $state);
        $query = $this->applySearch($query, $state);

        return $query;
    }

    /**
     * Resolve model class names and relations into a builder instance.
     */
    protected function resolveQuery(Builder|Relation|string $query): Builder
    {
        if ($query instanceof Relation) {
            return $query->getQuery();
        }

        if (is_string($query)) {
            if (! class_exists($query) || ! is_subclass_of($query, Model::class)) {
                throw new \InvalidArgumentException('Invalid model class: '.$query);
            }

            return $query::query();
        }

        return $query;
    }

    /**
     * Apply configured sorting or custom model sort methods.
     */
    protected function applySorting(Builder $query, array $state): Builder
    {
        $sort = $state['sort'] ?? [];

        if (! is_array($sort) || $sort === []) {
            return $query;
        }

        $model = $query->getModel();

        foreach ($sort as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $field = $definition['field'] ?? null;
            $direction = $definition['direction'] ?? null;

            if (! is_string($field) || ! is_string($direction)) {
                continue;
            }

            $direction = strtolower(trim($direction));

            if ($direction !== 'asc' && $direction !== 'desc') {
                continue;
            }

            $isDesc = $direction === 'desc';
            $method = 'orderBy'.Str::studly($field);

            if ($this->hasCustomSortMethod($model, $method)) {
                $query = $model->{$method}($query, $isDesc);
            } else {
                $query = $query->orderBy($field, $isDesc ? 'desc' : 'asc');
            }
        }

        return $query;
    }

    /**
     * Check whether a model defines a callable custom sort method.
     */
    protected function hasCustomSortMethod(object $model, string $method): bool
    {
        if (! method_exists($model, $method)) {
            return false;
        }

        if (! is_callable([$model, $method])) {
            return false;
        }

        return true;
    }

    /**
     * Apply search scope when search is enabled and non-empty.
     */
    protected function applySearch(Builder $query, array $state): Builder
    {
        if (! ($state['searchable'] ?? false)) {
            return $query;
        }

        $search = $state['search'] ?? null;

        if ($search === null || (is_string($search) && trim($search) === '')) {
            return $query;
        }

        return $query->{$this->searchScopeName}($search);
    }
}
