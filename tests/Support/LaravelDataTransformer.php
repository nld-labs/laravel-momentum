<?php

namespace NLD\Momentum\Tests\Support;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

class LaravelDataTransformer extends Data
{
    #[Computed]
    public bool $transformed;

    public function __construct(
        public int $id,
        public string $name,
    ) {
        $this->transformed = true;
    }
}
