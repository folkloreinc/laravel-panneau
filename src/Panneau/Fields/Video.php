<?php

namespace Panneau\Fields;

class Video extends Upload
{
    public function component(): string
    {
        return 'video';
    }
}
