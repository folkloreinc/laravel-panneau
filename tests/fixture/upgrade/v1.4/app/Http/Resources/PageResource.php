<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id(),
            'title' => $request->isPanneau() ? $this->title() : $this->title(app()->getLocale()),
            'blocks' => $this->when(! $request->isPanneauIndex(), fn () => $this->blocks()),
        ];
    }
}
