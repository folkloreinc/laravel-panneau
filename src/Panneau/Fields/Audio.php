<?php

namespace Panneau\Fields;

class Audio extends Media
{
    public function component(): string
    {
        return $this->uploadOnly ? 'audio_upload' : 'audio';
    }
}
