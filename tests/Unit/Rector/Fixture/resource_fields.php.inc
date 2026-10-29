<?php

namespace App\Panneau\Resources;

use Panneau\Fields\TextLocalized;
use Panneau\Fields\Toggle;
use Panneau\Fields\Html;
use Panneau\Support\Resource;

class Pages extends Resource
{
    public function fields(): array
    {
        return [
            Toggle::make('published')->withTransLabel('panneau.fields.published')->hideInForm(),
            TextLocalized::make('description')
                ->isRequired()
                ->isTextarea()
                ->withTransHelpText('panneau.help_texts.description', ['max' => 140])
                ->withTransLabel('panneau.fields.description'),
            Html::make('body', 'Body'),
        ];
    }
}

?>
-----
<?php

namespace App\Panneau\Resources;

use Panneau\Resources\Resource;
use Panneau\Fields\Boolean;
use Panneau\Fields\Textarea;
use Panneau\Fields\RichText;

class Pages extends Resource
{
    public function fields(): array
    {
        return [
            // TODO(panneau-5): 'published' was hidden from the form (hideInForm()): 5.x has no field that shows in lists only
            Boolean::make('published')->label(__('panneau.fields.published')),
            Textarea::make('description')->localized()
                ->required()->help(__('panneau.help_texts.description', ['max' => 140]))
                ->label(__('panneau.fields.description')),
            RichText::make('body')->label('Body'),
        ];
    }
}

?>
