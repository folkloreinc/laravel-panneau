<?php

namespace Panneau\Fields;

class Video extends Media
{
    public function component(): string
    {
        return $this->uploadOnly ? 'video_upload' : 'video';
    }

    public function components(): ?array
    {
        return [
            'display' => 'video',
        ];
    }
}
