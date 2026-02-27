<?php

namespace NLD\Momentum\Tests\Support;

use Illuminate\Support\Collection;

class CustomTransformer
{
    public static function collection(Collection $items): Collection
    {
        return $items->map(fn ($item) => self::transform($item));
    }

    public static function transform($resource): array
    {
        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'transformed' => true,
        ];
    }
}
