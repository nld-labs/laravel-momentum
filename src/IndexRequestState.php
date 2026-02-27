<?php

declare(strict_types=1);

namespace NLD\Momentum;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Resolves index-related request state from controller config and input.
 */
class IndexRequestState
{
    protected ?array $resolved = null;

    protected string $searchParamName;

    protected string $sortParamName;

    /**
     * Create a state resolver from controller config and current request.
     */
    public function __construct(
        protected array $indexConfig,
        protected Request $request
    ) {
        $this->searchParamName = config('momentum.paramNames.search', 'search');
        $this->sortParamName = config('momentum.paramNames.sort', 'sort');
    }

    /**
     * Resolve and cache the normalized index state payload.
     */
    public function resolve(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $state = array_merge(
            $this->resolveSearchable(),
            $this->resolveSortable(),
            $this->resolveParams(),
        );

        $this->resolved = $state;

        return $state;
    }

    /**
     * Get request parameter names that should be persisted in session.
     */
    public function getPersistedParamNames(): array
    {
        $state = $this->resolve();
        $names = [];

        if ($state['searchable'] ?? false) {
            $names[] = $this->searchParamName;
        }

        if ($state['sortable'] ?? false) {
            $names[] = $this->sortParamName;
        }

        if ($state['params'] ?? false) {
            $names = array_merge($names, array_keys($state['params']));
        }

        return $names;
    }

    /**
     * Resolve search-related state.
     */
    protected function resolveSearchable(): array
    {
        if (! ($this->indexConfig['searchable'] ?? false)) {
            return [];
        }

        return [
            'searchable' => true,
            'search' => $this->request->get($this->searchParamName),
        ];
    }

    /**
     * Resolve sorting configuration and current sort state.
     */
    protected function resolveSortable(): array
    {
        $sortable = $this->indexConfig['sortable'] ?? false;

        if (! $sortable) {
            return [];
        }

        if (is_string($sortable)) {
            $sortable = Str::of($sortable)->split('/[\s,]+/')->toArray();
        }

        if (! is_array($sortable) || count($sortable) === 0) {
            return [];
        }

        $defaultSort = $this->indexConfig['defaultSort'] ?? [];
        $defaultSortFields = $this->normalizeDefaultSort($defaultSort, $sortable);

        $rawSort = $this->request->get($this->sortParamName);
        $sortFields = $rawSort === null
            ? $defaultSortFields
            : $this->parseSort($rawSort, $sortable);

        return [
            'sortable' => $sortable,
            'sort' => $sortFields,
            'defaultSort' => $defaultSortFields,
        ];
    }

    /**
     * Normalize default sort config into field directions.
     */
    protected function normalizeDefaultSort(mixed $defaultSort, array $sortable): array
    {
        if (! is_array($defaultSort)) {
            return [];
        }

        $sortFields = [];

        foreach ($defaultSort as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $field = $definition['field'] ?? null;
            $direction = $definition['direction'] ?? null;

            if (! is_string($field) || ! in_array($field, $sortable, true)) {
                continue;
            }

            if (! is_string($direction)) {
                continue;
            }

            $direction = strtolower(trim($direction));

            if ($direction !== 'asc' && $direction !== 'desc') {
                continue;
            }

            $sortFields = $this->mergeSortField($sortFields, [
                'field' => $field,
                'direction' => $direction,
            ]);
        }

        return $sortFields;
    }

    /**
     * Parse a comma-delimited sort string into field directions.
     */
    protected function parseSort(mixed $rawSort, array $sortable): array
    {
        if (! is_string($rawSort)) {
            return [];
        }

        $sortFields = [];

        foreach (explode(',', $rawSort) as $sortBy) {
            $field = $this->parseSortField($sortBy, $sortable);

            if ($field !== null) {
                $sortFields = $this->mergeSortField($sortFields, $field);
            }
        }

        return $sortFields;
    }

    /**
     * Parse a single sort token and validate it against allowed fields.
     * Defaults to ascending when no direction modifier is provided.
     */
    protected function parseSortField(mixed $rawField, array $sortable): ?array
    {
        if (! is_string($rawField) || trim($rawField) === '') {
            return null;
        }

        $parts = explode('!', trim($rawField), 2);

        $name = trim($parts[0]);
        $direction = count($parts) === 2
            ? strtolower(trim($parts[1]))
            : 'asc';

        if ($name === '' || ! in_array($name, $sortable, true)) {
            return null;
        }

        if ($direction !== 'asc' && $direction !== 'desc') {
            return null;
        }

        return [
            'field' => $name,
            'direction' => $direction,
        ];
    }

    /**
     * Merge a single parsed sort field into an ordered unique sort list.
     */
    protected function mergeSortField(array $sortFields, array $field): array
    {
        foreach ($sortFields as $index => $existing) {
            if (($existing['field'] ?? null) !== $field['field']) {
                continue;
            }

            $sortFields[$index]['direction'] = $field['direction'];

            return $sortFields;
        }

        $sortFields[] = $field;

        return $sortFields;
    }

    /**
     * Resolve additional configured index parameters from request input.
     */
    protected function resolveParams(): array
    {
        $params = $this->indexConfig['params'] ?? false;

        if (! $params) {
            return [];
        }

        if (is_string($params)) {
            $params = Str::of($params)->split('/[\s,]+/')->toArray();
        }

        if (! is_array($params)) {
            return [];
        }

        $resolvedParams = [];

        foreach ($params as $definition) {
            [$name, $type] = array_pad(explode(':', $definition, 2), 2, null);

            if ($type === 'int') {
                $value = $this->request->has($name)
                    ? $this->request->integer($name)
                    : null;
            } elseif ($type === 'bool') {
                $value = $this->request->has($name)
                    ? $this->request->boolean($name)
                    : null;
            } else {
                $value = $this->request->get($name);
            }

            $resolvedParams[$name] = $value;
        }

        return ['params' => $resolvedParams];
    }

    /**
     * Build state resolver using a controller's index configuration.
     */
    public static function fromController(object $controller, Request $request): self
    {
        $indexConfig = property_exists($controller, 'indexConfig')
            ? $controller->indexConfig
            : [];

        return new self($indexConfig, $request);
    }
}
