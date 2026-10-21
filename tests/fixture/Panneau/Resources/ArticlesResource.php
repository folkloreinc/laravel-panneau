<?php

namespace TestApp\Panneau\Resources;

use Panneau\Fields\BlockType;
use Panneau\Fields\Media;
use Panneau\Fields\Repeater;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use TestApp\Models\Article;

/**
 * An item with media fields.
 */
class ArticlesResource extends Resource
{
    public function model(): string
    {
        return Article::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->required(),
            Media::make('cover')->resource(ImagesResource::class)->accept(['image/png', 'image/jpeg'])->maxSize(500_000),
            Media::make('gallery')->resource(ImagesResource::class)->multiple()->max(3),
            Media::make('attachment')->resource(DocumentsResource::class)->transforms(false),
            Repeater::make('blocks')->types([
                BlockType::make('image', 'Image', [Media::make('image')->resource(ImagesResource::class)->required()]),
            ]),
        ];
    }
}
