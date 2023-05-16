<?php

namespace Panneau\Fields;

use Panneau\Support\LocalizedField;

class DocumentLocalized extends LocalizedField
{
    protected $endpoint = false;

    protected $withButton = false;

    public function field($locale)
    {
        $document = new Document($locale);
        if ($this->withButton) {
            $document->withButton();
        }
        if (isset($this->endpoint)) {
            $document->withEndpoint($this->endpoint);
        }
        return $document;
    }

    public function components(): ?array
    {
        return [
            'display' => 'document',
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
