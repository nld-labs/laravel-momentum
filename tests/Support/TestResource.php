<?php

namespace NLD\Momentum\Tests\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use NLD\Momentum\Traits\ResourceTransformer;

class TestResource extends JsonResource
{
    use ResourceTransformer;

    public $fakeBuildOptions = [
        'append' => ['test' => 'value'],
    ];

    public function toArray(Request $request): array
    {
        return $this->formatData($request, $this->fakeBuildOptions);
    }
}
