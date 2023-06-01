<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class UploadLocalized extends LocalizedField
{
    protected $endpoint = false;

    protected $withButton = false;

    protected $withFind = false;

    public function field($locale)
    {
        $upload = new Upload($locale);
        if ($this->withButton) {
            $upload->withButton();
        }
        if ($this->withFind) {
            $upload->withFind();
        }
        if (isset($this->endpoint)) {
            $upload->withEndpoint($this->endpoint);
        }
        return $upload;
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

    public function withFind()
    {
        $this->withFind = true;
        return $this;
    }
}
