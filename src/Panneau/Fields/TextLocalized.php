<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class TextLocalized extends LocalizedField
{
    protected $textarea = false;

    protected $disabled = false;

    public function field($locale)
    {
        $field = new Text($locale);
        if ($this->textarea) {
            $field->isTextarea();
        }
        if ($this->disabled) {
            $field->isDisabled();
        }
        return $field;
    }

    public function components(): ?array
    {
        return [
            'display' => 'text-localized',
        ];
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

    public function isDisabled()
    {
        $this->disabled = true;
        return $this;
    }

    public function isEnabled()
    {
        $this->disabled = false;
        return $this;
    }
}
