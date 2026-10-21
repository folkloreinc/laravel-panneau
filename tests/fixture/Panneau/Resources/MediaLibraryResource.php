<?php

namespace TestApp\Panneau\Resources;

use Illuminate\Http\Request;
use Panneau\Fields\Text;
use Panneau\Mediatheque\MediathequeResource;

/**
 * A library on laravel-mediatheque, of the images and documents of a team: the media table has
 * a `team_id` column. Only used when the mediatheque is installed.
 */
class MediaLibraryResource extends MediathequeResource
{
    public function mediaTypes(): array
    {
        return ['image', 'document'];
    }

    public function fields(): array
    {
        return [Text::make('name')];
    }

    public function scope(Request $request): array
    {
        return ['team_id' => 1];
    }
}
