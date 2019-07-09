<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class LinkLocalized extends Field
{
    protected function getLocales()
    {
        return app('panneau')->locales();
    }

    protected function properties()
    {
        return [
            'url' => field('url_localized'),
            'label' => field('text_localized'),
        ];
    }

    protected function attributes()
    {
        return [
            'locales' => $this->getLocales(),
        ];
    }
}
