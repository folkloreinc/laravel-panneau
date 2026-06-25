<?php

namespace Panneau\Fields;

class Medias extends Uploads
{
    protected $uploadOnly = false;

    public function field(): ?string
    {
        return $this->uploadOnly ? Upload::class : Media::class;
    }

    public function uploadOnly()
    {
        $this->uploadOnly = true;
        return $this;
    }
}
