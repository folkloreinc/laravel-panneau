<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class AudioLocalized extends LocalizedField
{
    protected $endpoint = false;

    protected $withButton = false;

    public function field($locale)
    {
        $audio = new Audio($locale);
        if ($this->withButton) {
            $audio->withButton();
        }
        if (isset($this->endpoint)) {
            $audio->withEndpoint($this->endpoint);
        }
        return $audio;
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
