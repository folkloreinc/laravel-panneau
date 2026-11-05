<?php

namespace TestApp\Repositories;

use Panneau\Exceptions\ItemInUseException;
use Panneau\Repositories\SoftDeletingEloquentRepository;
use TestApp\Models\Document;

/**
 * Documents with a trash, whose repository refuses to delete those named "In use", as a
 * repository does for an item that something else uses.
 *
 * @extends SoftDeletingEloquentRepository<Document>
 */
class DocumentsInUseRepository extends SoftDeletingEloquentRepository
{
    public function __construct()
    {
        parent::__construct(Document::class);
    }

    public function delete(object $item): void
    {
        if ($item instanceof Document && $item->getAttribute('name') === 'In use') {
            throw new ItemInUseException($item);
        }
        parent::delete($item);
    }
}
