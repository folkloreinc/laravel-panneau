<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class VideoLocalized extends LocalizedField
{
    protected $withButton = false;

    public function field($locale)
    {
        $video = new Video($locale);
        return $this->withButton ? $video->withButton() : $video;
    }

    public function components(): ?array
    {
        return [
            'display' => 'video',
        ];
    }

    public function withButton()
    {
        $this->withButton = true;
        return $this;
    }
}
