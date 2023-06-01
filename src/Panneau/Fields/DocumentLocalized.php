<?php

namespace Panneau\Fields;

class DocumentLocalized extends UploadLocalized
{
    public function field($locale)
    {
        $document = new Document($locale);
        if ($this->withButton) {
            $document->withButton();
        }
        if ($this->withFind) {
            $document->withFind();
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
}
