<?php

namespace Panneau\Legacy\Fields;

class AudioLocalized extends MediaLocalized
{
    protected $fieldClass = Audio::class;

    protected $uploadFieldClass = AudioUpload::class;
}
