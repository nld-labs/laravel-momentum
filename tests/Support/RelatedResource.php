<?php

namespace NLD\Momentum\Tests\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use NLD\Momentum\Traits\ResourceTransformer;

class RelatedResource extends JsonResource
{
    use ResourceTransformer;

    public function toArray(Request $request): array
    {
        return $this->resolveData($request, [
            'pick' => ['id', 'name'],
        ]);
    }
}
