<?php

namespace Panneau\Fields;

class ImageLocalized extends UploadLocalized
{
    public function field($locale)
    {
        $image = new Image($locale);
        if ($this->withButton) {
            $image->withButton();
        }
        if ($this->withFind) {
            $image->withFind();
        }
        if (isset($this->endpoint)) {
            $image->withEndpoint($this->endpoint);
        }
        return $image;
    }

    public function components(): ?array
    {
        return [
            'display' => 'image',
        ];
    }
}
