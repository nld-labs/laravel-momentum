<?php

declare(strict_types=1);

namespace NLD\Momentum;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Inertia\Inertia;
use NLD\Momentum\Contracts\MomentumController;
use NLD\Momentum\Transformers\ItemTransformer;

/**
 * Builds index responses with filtering, pagination, and transformation.
 */
class IndexResponseBuilder
{
    protected bool $shouldPaginate = false;

    protected ?int $perPage = null;

    protected bool $shouldUsePerPageQuery = false;

    protected ?string $perPageQueryParam = null;

    protected \Closure|string|null $transformer = null;

    /**
     * Initialize a response builder for a controller and query source.
     */
    public function __construct(
        protected MomentumController $controller,
        public Builder|Relation|string $query,
        protected ItemTransformer $itemTransformer = new ItemTransformer,
    ) {}

    /**
     * Enable pagination for the response data.
     */
    public function paginate(?int $perPage = null): self
    {
        $this->shouldPaginate = true;
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Read pagination size from query string when present.
     */
    public function withPerPageQuery(?string $param = null): self
    {
        $this->shouldUsePerPageQuery = true;
        $this->perPageQueryParam = $param;

        return $this;
    }

    /**
     * Set a transformer closure or class for returned items.
     */
    public function transform(\Closure|string|null $transformer): self
    {
        $this->transformer = $transformer;

        return $this;
    }

    /**
     * Render the Inertia response with resolved index meta.
     */
    public function render(string $component, array $props = []): mixed
    {
        $query = $this->controller->buildIndexQuery($this->query);

        if ($this->shouldPaginate) {
            $perPage = $this->resolvePerPage();
            $paginated = $perPage === null
                ? $query->paginate()
                : $query->paginate($perPage);

            if ($this->shouldUsePerPageQuery) {
                $this->controller->persistIndexParam(
                    $this->resolvePerPageQueryParam(),
                    $paginated->perPage(),
                );
            }

            if ($paginated->currentPage() < 1 || $paginated->currentPage() > $paginated->lastPage()) {
                return $this->controller->redirect(
                    $paginated->lastPage() > 1 ? $paginated->lastPage() : 1
                );
            }

            $this->controller->persistIndexParam('page', $paginated->currentPage());
            $items = $paginated->getCollection();
            $pagination = [
                'total' => $paginated->total(),
                'perPage' => $paginated->perPage(),
                'currentPage' => $paginated->currentPage(),
                'totalPages' => $paginated->lastPage(),
            ];
        } else {
            $items = $query->get();
            $pagination = null;
        }

        $items = $this->itemTransformer->transform($items, $this->transformer);

        $this->controller->persistIndexParams();

        return Inertia::render(
            $component,
            array_merge(
                $props,
                [
                    'data' => $items,
                    'meta' => [
                        ...$this->controller->resolveIndexState(),
                        'pagination' => $pagination,
                    ],
                ]
            ),
        );
    }

    /**
     * Resolve the effective per-page value.
     */
    protected function resolvePerPage(): ?int
    {
        if (! $this->shouldUsePerPageQuery) {
            return $this->normalizePerPageValue($this->perPage);
        }

        $param = $this->resolvePerPageQueryParam();
        $requestPerPage = $this->normalizePerPageValue(request()->query($param));

        if ($requestPerPage !== null) {
            return $requestPerPage;
        }

        $persistedPerPage = $this->normalizePerPageValue($this->controller->getPersistedParam($param));

        if ($persistedPerPage !== null) {
            return $persistedPerPage;
        }

        return $this->normalizePerPageValue($this->perPage);
    }

    /**
     * Resolve the query parameter name used for per-page values.
     */
    protected function resolvePerPageQueryParam(): string
    {
        return $this->perPageQueryParam
            ?? config('momentum.paramNames.perPage', 'perPage');
    }

    /**
     * Normalize per-page values and reject invalid input.
     */
    protected function normalizePerPageValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }
}
