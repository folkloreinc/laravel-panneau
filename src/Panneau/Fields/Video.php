<?php

namespace Panneau\Fields;

class Video extends Upload
{
    public function component(): string
    {
        return 'video';
    }

    public function components(): ?array
    {
        return [
            'display' => 'video',
        ];
    }
}
