<?php

namespace Panneau\Legacy\Fields;

class VideoLocalized extends MediaLocalized
{
    protected $fieldClass = Video::class;

    protected $uploadFieldClass = VideoUpload::class;
}
