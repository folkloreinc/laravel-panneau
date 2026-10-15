<?php

namespace Panneau\Legacy\Fields;

class DocumentLocalized extends MediaLocalized
{
    protected $fieldClass = Document::class;

    protected $uploadFieldClass = DocumentUpload::class;
}
