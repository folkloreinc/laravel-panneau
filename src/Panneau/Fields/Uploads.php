<?php

namespace Panneau\Fields;

class Uploads extends Items
{
    protected $withButton = false;

    public function field(): ?string
    {
        return Upload::class;
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'itemProps' => [
                'withButton' => $this->withButton,
            ],
        ]);
    }

    public function withButton()
    {
        $this->withButton = true;
        return $this;
    }
}
