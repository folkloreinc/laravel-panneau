<?php

namespace App\Panneau\Resources;

use Panneau\Resources\Resource;
use Panneau\Fields\Url;
use Panneau\Fields\Relation;
use Panneau\Fields\Color;
use Panneau\Fields\Select;

class Links extends Resource
{
    public function fields(): array
    {
        return [
            // TODO(panneau-5): 'link' called withFieldAttributes(), which Url doesn't have in 5.x: settings(), or a JS plugin
            Url::make('link')->localized()->label(__('fields.link')),
            // TODO(panneau-5): 'color' called withAlpha(), which Color doesn't have in 5.x: settings(), or a JS plugin
            Color::make('color'),
            // TODO(panneau-5): 'page' called asItem(), which Relation doesn't have in 5.x: settings(), or a JS plugin
            // TODO(panneau-5): 'page' called paginated(), which Relation doesn't have in 5.x: settings(), or a JS plugin
            Relation::make('page')->resource('pages'),
            // TODO(panneau-5): 'tags' called isMultiple(), which Select doesn't have in 5.x: settings(), or a JS plugin
            Select::make('tags')->options(['a' => 'A']),
            Select::make('size')->multiple(),
        ];
    }
}

?>
