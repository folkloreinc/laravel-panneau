<?php

namespace TestApp\Panneau\Resources;

use Illuminate\Http\Request;
use Panneau\Fields\File;
use Panneau\Fields\Text;
use Panneau\Resources\MediaResource;
use TestApp\Models\Asset;

/**
 * An Eloquent library of documents, on the same table as the images: its media have a
 * `library` column.
 */
class DocumentsResource extends MediaResource
{
    public function model(): string
    {
        return Asset::class;
    }

    public function mediaTypes(): array
    {
        return ['document'];
    }

    protected function file(File $file): File
    {
        return $file->disk('files')->directory('documents');
    }

    public function fields(): array
    {
        return [Text::make('name')];
    }

    public function scope(Request $request): array
    {
        return ['team_id' => 1, 'library' => 'documents'];
    }
}
