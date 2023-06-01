<?php

namespace Panneau\Fields;

class AudioLocalized extends UploadLocalized
{
    public function field($locale)
    {
        $audio = new Audio($locale);
        if ($this->withButton) {
            $audio->withButton();
        }
        if ($this->withFind) {
            $audio->withFind();
        }
        if (isset($this->endpoint)) {
            $audio->withEndpoint($this->endpoint);
        }
        return $audio;
    }

    public function components(): ?array
    {
        return [
            'display' => 'image',
        ];
    }
}
