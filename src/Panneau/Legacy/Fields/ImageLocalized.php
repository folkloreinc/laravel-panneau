<?php

namespace Panneau\Legacy\Fields;

class ImageLocalized extends MediaLocalized
{
    protected $fieldClass = Image::class;

    protected $uploadFieldClass = ImageUpload::class;
}
