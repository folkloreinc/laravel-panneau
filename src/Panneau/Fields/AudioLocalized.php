<?php

namespace Panneau\Fields;

class AudioLocalized extends UploadLocalized
{
    public function field($locale)
    {
        $field = new Audio($locale);
        if ($this->withButton) {
            $field->withButton();
        }
        if ($this->withFind) {
            $field->withFind();
        }
        if (isset($this->endpoint)) {
            $field->withEndpoint($this->endpoint);
        }
        if ($this->disabled) {
            $field->isDisabled();
        }
        return $field;
    }

    public function components(): ?array
    {
        return [
            'display' => 'image',
        ];
    }
}
