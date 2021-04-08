<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Text extends Field
{
    protected $textarea = false;

    public function type(): string
    {
        return 'string';
    }

    public function component(): string
    {
        return $this->textarea ? 'textarea' : 'text';
    }

    public function isTextarea()
    {
        $this->textarea = true;
        return $this;
    }

    public function isText()
    {
        $this->textarea = false;
        return $this;
    }
}
