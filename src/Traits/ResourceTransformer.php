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
     * Format transformed data with optional field and relation controls.
     */
    protected function formatData(Request $request, array $options = []): array
    {
        if ($this->resource === null) {
            return [];
        }

        $data = parent::toArray($request);

        if (array_key_exists('only', $options)) {
            $data = Arr::only($data, $options['only']);
        }

        if (array_key_exists('except', $options)) {
            $data = Arr::except($data, $options['except']);
        }

        if (array_key_exists('append', $options)) {
            $data = [...$data, ...$options['append']];
        }

        if (array_key_exists('loaded', $options)) {
            foreach ($options['loaded'] as $relation => $resource) {
                if ($this->relationLoaded($relation)) {
                    $related = $this->{$relation};

                    if ($related instanceof Collection) {
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
