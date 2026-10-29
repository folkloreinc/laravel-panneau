<?php

namespace App\Panneau\Resources;

use Panneau\Resources\Resource;
use Panneau\Fields\Media;
use Panneau\Fields\Relation;
use Panneau\Fields\Group;
use Panneau\Fields\Tel;
use Folklore\Panneau\Fields\PageSlug;
use Folklore\Panneau\Fields\Blocks;
use Panneau\Fields\Image;
use Panneau\Fields\Password;
use Panneau\Fields\Select;
use Panneau\Fields\Text;

class Venues extends Resource
{
    public function fields(): array
    {
        return [
            Media::make('file'),
            Image::make('cover')->label(__('fields.cover')),
            Image::make('gallery')->multiple(),
            Media::make('trailer')->accept(['video/*'])->localized(),
            Media::make('podcast')->accept(['audio/*']),
            // TODO(panneau-5): 'press' is documents: name a library of documents with resource(), or accept()
            Media::make('press')->multiple(),
            Media::make('poster')->localized(),
            // TODO(panneau-5): 'owner' is a relation: name the related resource with resource()
            Relation::make('owner'),
            Relation::make('city')->resource('cities'),
            Relation::make('events')->multiple(),
            Group::make('address')->fields([
                Text::make('street')->label(__('fields.street')),
                // TODO(panneau-5): 'city' was required in some locales (isRequired([...])): 5.x requires every locale
                Text::make('city')->localized()->required(),
            ]),
            Select::make('status')->options(['open' => 'Open', 'closed' => 'Closed'])->default('open'),
            Select::make('size')->options(['s', 'm', 'l']),
            // TODO(panneau-5): 'phone' was only for some types (onlyForTypes()): resource types are not built yet in 5.x
            Tel::make('phone'),
            // TODO(panneau-5): 'code' is Password: 5.x has no password field
            Password::make('code'),
            // TODO(panneau-5): 'notes' had attributes for the JS (withAttributes()): settings(), or a JS plugin
            Text::make('notes'),
            PageSlug::make('slug')->readOnly(),
            // TODO(panneau-5): 'blocks' left out a type (withoutType()): exceptTypes('id'), by id
            Blocks::make('blocks')->onlyTypes(['text']),
            Image::make('logo'),
        ];
    }

    public function extraField()
    {
        $field = Text::make('tagline')->localized();

        return $field;
    }
}

?>
