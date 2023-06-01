<?php

namespace Panneau\Fields;

class VideoLocalized extends UploadLocalized
{
    public function field($locale)
    {
        $video = new Video($locale);
        if ($this->withButton) {
            $video->withButton();
        }
        if ($this->withFind) {
            $video->withFind();
        }
        return $video;
    }

    public function components(): ?array
    {
        return [
            'display' => 'video',
        ];
    }
}
