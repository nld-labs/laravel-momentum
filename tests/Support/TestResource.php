<?php

namespace NLD\Momentum\Tests\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use NLD\Momentum\Traits\ResourceTransformer;

class TestResource extends JsonResource
{
    use ResourceTransformer;

    public $fakeBuildOptions = [
        'extra' => ['test' => 'value'],
    ];

    public function toArray(Request $request): array
    {
        return $this->resolveData($request, $this->fakeBuildOptions);
    }
}
