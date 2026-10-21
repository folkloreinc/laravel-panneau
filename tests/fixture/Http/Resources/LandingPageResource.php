<?php

namespace TestApp\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LandingPageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id(),
            'type' => 'landing',
            'title' => $this->title(),
        ];
    }
}
