<?php

namespace Panneau\Fields;

class AudioLocalized extends MediaLocalized
{
    protected $fieldClass = Audio::class;
    protected $uploadFieldClass = AudioUpload::class;
}
