<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Panneau\Contracts\ResourceItem;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource instanceof ResourceItem ? $this->resource->id() : null,
            'number' => $this->number(),
        ];
    }
}
