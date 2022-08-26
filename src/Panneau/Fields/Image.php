<?php

namespace Panneau\Fields;

class Image extends Upload
{
    public function component(): string
    {
        return 'image';
    }

    public function components(): ?array
    {
        return [
            'display' => 'image',
        ];
    }
}
