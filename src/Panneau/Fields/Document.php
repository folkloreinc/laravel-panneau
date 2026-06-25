<?php

namespace Panneau\Fields;

class Document extends Media
{
    public function component(): string
    {
        return $this->uploadOnly ? 'document_upload' : 'document';
    }
}
