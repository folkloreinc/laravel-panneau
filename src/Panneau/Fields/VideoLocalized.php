<?php

namespace Panneau\Fields;

class VideoLocalized extends UploadLocalized
{
    public function field($locale)
    {
        $field = new Video($locale);
        if ($this->withButton) {
            $field->withButton();
        }
        if ($this->withFind) {
            $field->withFind();
        }
        if ($this->disabled) {
            $field->isDisabled();
        }
        return $field;
    }

    public function components(): ?array
    {
        return [
            'display' => 'video',
        ];
    }
}
