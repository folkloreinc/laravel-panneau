<?php

namespace Panneau\Fields;

class Image extends Media
{
    public function component(): string
    {
        return $this->uploadOnly ? 'image_upload' : 'image';
    }

    public function components(): ?array
    {
        return [
            'display' => 'image',
        ];
    }
}
