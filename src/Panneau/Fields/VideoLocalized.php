<?php

namespace Panneau\Fields;

class VideoLocalized extends MediaLocalized
{
    protected $fieldClass = Video::class;
    protected $uploadFieldClass = VideoUpload::class;
}
