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
        $field = new Upload($locale);
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
