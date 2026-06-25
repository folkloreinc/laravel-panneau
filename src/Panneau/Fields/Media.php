<?php

namespace Panneau\Fields;

class Media extends Upload
{
    protected bool $uploadOnly = false;

    public function component(): string
    {
        return $this->uploadOnly ? 'upload' : 'media';
    }

    public function uploadOnly()
    {
        $this->uploadOnly = true;
        return $this;
    }
}
