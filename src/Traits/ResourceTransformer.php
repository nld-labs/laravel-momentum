<?php

declare(strict_types=1);

namespace NLD\Momentum\Traits;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Adds lightweight formatting helpers for JSON resource transformers.
 */
trait ResourceTransformer
{
    /**
     * Transform a paginator collection while preserving pagination metadata.
     */
    public static function paginated(LengthAwarePaginator $paginated): LengthAwarePaginator
    {
        $paginated->setCollection(
            collect(self::collection($paginated))
        );

        return $paginated;
    }

    /**
     * Resolve transformed data with optional field and relation controls.
     *
     * @param array{
     *     pick?: list<string>,
     *     hide?: list<string>,
     *     extra?: array<string, mixed|\Closure>,
     *     relations?: array<string, class-string|\Closure>
     * } $options
     */
    protected function resolveData(Request $request, array $options = []): array
    {
        if ($this->resource === null) {
            return [];
        }

        $data = parent::toArray($request);

        if (isset($options['pick'])) {
            $data = Arr::only($data, $options['pick']);
        }

        if (isset($options['hide'])) {
            $data = Arr::except($data, $options['hide']);
        }

        if (isset($options['extra'])) {
            $extra = $options['extra'];
            foreach ($extra as $key => $value) {
                $data[$key] = is_callable($value) ? $value($this->resource) : $value;
            }
        }

        if (isset($options['relations'])) {
            foreach ($options['relations'] as $relation => $resource) {
                if ($this->relationLoaded($relation)) {
                    $related = $this->{$relation};

                    if (is_callable($resource)) {
                        $data[$relation] = $resource($this->resource, $related);
                    } elseif ($related instanceof Collection) {
                        $data[$relation] = $resource::collection($related);
                    } else {
                        $data[$relation] = $resource::make($related);
                    }
                }
            }
        }

        return $data;
    }
}
