<?php

declare(strict_types=1);

namespace NLD\Momentum\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Applies configured collection transformers to index results.
 */
class ItemTransformer
{
    /**
     * Transform items with a closure or transformer class.
     */
    public function transform(mixed $items, \Closure|string|null $transformer): mixed
    {
        if ($transformer === null) {
            return $items;
        }

        if ($transformer instanceof \Closure) {
            return $transformer($items);
        }

        return $this->transformWithClass($items, $transformer);
    }

    /**
     * Transform items using a supported transformer class.
     */
    protected function transformWithClass(mixed $items, string $transformerClass): mixed
    {
        if (! class_exists($transformerClass)) {
            throw new \InvalidArgumentException("Transformer class does not exist: {$transformerClass}");
        }

        if (is_subclass_of($transformerClass, JsonResource::class)) {
            return $transformerClass::collection($items);
        }

        if ($this->isLaravelDataClass($transformerClass)) {
            return $transformerClass::collect($items);
        }

        if (is_callable([$transformerClass, 'collection'])) {
            return $transformerClass::collection($items);
        }

        throw new \InvalidArgumentException(
            "Transformer must be a JsonResource, a Laravel Data class, or define a static collection() method: {$transformerClass}"
        );
    }

    /**
     * Determine whether the class extends Spatie Laravel Data.
     */
    protected function isLaravelDataClass(string $class): bool
    {
        return class_exists(\Spatie\LaravelData\Data::class)
            && is_subclass_of($class, \Spatie\LaravelData\Data::class);
    }
}
