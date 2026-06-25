<?php

namespace Panneau\Fields;

class ImageLocalized extends MediaLocalized
{
    protected $fieldClass = Image::class;
    protected $uploadFieldClass = ImageUpload::class;
}
