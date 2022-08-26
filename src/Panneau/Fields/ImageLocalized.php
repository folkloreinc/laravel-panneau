<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class ImageLocalized extends LocalizedField
{
    protected $endpoint = false;

    protected $withButton = false;

    public function field($locale)
    {
        $image = new Image($locale);
        if ($this->withButton) {
            $image->withButton();
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

    public function withEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function withButton()
    {
        $this->withButton = true;
        return $this;
    }
}
