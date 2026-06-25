<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class UploadLocalized extends LocalizedField
{
    protected ?string $endpoint = null;

    protected $withButton = false;

    public function field(string $locale)
    {
        $field = new Upload($locale);
        if ($this->withButton) {
            $field->withButton();
        }
        if (isset($this->endpoint)) {
            $field->withEndpoint($this->endpoint);
        }
        if ($this->disabled) {
            $field->isDisabled();
        }
        return $field;
    }

    public function withEndpoint(?string $endpoint)
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
