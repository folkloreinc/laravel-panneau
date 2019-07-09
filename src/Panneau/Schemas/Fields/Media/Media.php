<?php

namespace Panneau\Schemas\Fields\Media;

use Panneau\Support\Schemas\ModelField;

class Media extends ModelField
{
    protected function fieldModel()
    {
        return \Folklore\Mediatheque\Contracts\Models\Media::class;
    }
}
