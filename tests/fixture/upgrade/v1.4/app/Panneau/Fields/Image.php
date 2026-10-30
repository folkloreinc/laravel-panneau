<?php

namespace App\Panneau\Fields;

use Panneau\Fields\Image as PanneauImage;

class Image extends PanneauImage
{
    public function component(): string
    {
        return 'image-with-credits';
    }
}
