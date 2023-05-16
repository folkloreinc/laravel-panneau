<?php

namespace Panneau\Fields;

class Document extends Upload
{
    public function component(): string
    {
        return 'document';
    }
}
