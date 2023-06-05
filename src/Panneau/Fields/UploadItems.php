<?php

namespace Panneau\Fields;

class UploadItems extends Items
{
    protected $withButton = false;

    protected $withFind = false;

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'itemProps' => [
                'withButton' => $this->withButton,
                'withFind' => $this->withFind,
            ]
        ]);
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
