<?php

namespace App\Panneau\Fields;

use Panneau\Fields\ImageUpload;

class ImageField extends ImageUpload
{
    public function component(): string
    {
        return 'custom_upload';
    }
}
