<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\Field;

class LinksLocalized extends Field
{
    protected function getLocales()
    {
        return app('panneau')->locales();
    }

    protected function type()
    {
        return 'array';
    }

    protected function items()
    {
        return field('link_localized');
    }

    protected function attributes()
    {
        return [
            'locales' => $this->getLocales(),
        ];
    }
}
