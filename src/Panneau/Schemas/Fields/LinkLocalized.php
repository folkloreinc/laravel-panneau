<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class LinkLocalized extends Field
{
    protected function getLocales()
    {
        return app('panneau')->locales();
    }

    protected function fields()
    {
        return [
            UrlLocalized::make('url'),
            TextLocalized::make('label'),
        ];
    }

    protected function attributes()
    {
        return [
            'locales' => $this->getLocales(),
        ];
    }
}
