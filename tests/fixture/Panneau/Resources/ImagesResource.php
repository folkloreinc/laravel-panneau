<?php

namespace TestApp\Panneau\Resources;

use Illuminate\Http\Request;
use Panneau\Fields\File;
use Panneau\Fields\Json;
use Panneau\Fields\Text;
use Panneau\Lists\ResourceList;
use Panneau\Resources\MediaResource;
use TestApp\Models\Asset;

/**
 * An Eloquent library of the images of a team, with their own transform.
 */
class ImagesResource extends MediaResource
{
    public function model(): string
    {
        return Asset::class;
    }

    public function mediaTypes(): array
    {
        return ['image'];
    }

    protected function file(File $file): File
    {
        return $file->disk('public')->directory('images')->visibility('public')->maxSize(1_000_000);
    }

    public function fields(): array
    {
        return [
            Text::make('name'),
            Text::make('alt')->localized(),
            // The media's own transform, which references give in their media
            Json::make('transform'),
        ];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['name'])->search(['name'])->sorts(['name']);
    }

    public function scope(Request $request): array
    {
        return ['team_id' => 1];
    }
}
