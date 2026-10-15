<?php

namespace Panneau\Legacy\Fields;

class Document extends Media
{
    public function component(): string
    {
        return $this->uploadOnly ? 'document_upload' : 'document';
    }
}
