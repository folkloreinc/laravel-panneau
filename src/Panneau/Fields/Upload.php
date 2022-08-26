<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Upload extends Field
{
    protected $endpoint = null;

    protected $withButton = false;

    public function type(): string
    {
        return 'object';
    }

    public function component(): string
    {
        return 'upload';
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'withButton' => $this->withButton,
            'endpoint' => $this->endpoint ?? route('panneau.upload'),
        ]);
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
