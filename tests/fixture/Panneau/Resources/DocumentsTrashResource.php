<?php

namespace TestApp\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use TestApp\Models\Document;

/**
 * A resource on a model that uses `SoftDeletes`: its `delete` is undone by `restore`.
 */
class DocumentsTrashResource extends Resource
{
    public function id(): string
    {
        return 'documents';
    }

    public function model(): string
    {
        return Document::class;
    }

    public function fields(): array
    {
        return [Text::make('name')->required()];
    }
}
