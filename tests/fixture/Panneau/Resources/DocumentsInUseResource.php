<?php

namespace TestApp\Panneau\Resources;

use TestApp\Repositories\DocumentsInUseRepository;

/**
 * Documents with a trash, whose repository refuses to delete those that are in use.
 */
class DocumentsInUseResource extends DocumentsTrashResource
{
    public function repository(): ?string
    {
        return DocumentsInUseRepository::class;
    }
}
