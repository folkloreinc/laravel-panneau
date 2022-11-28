<?php

namespace Panneau\Fields;

class Audio extends Upload
{
    public function component(): string
    {
        return 'audio';
    }
}
