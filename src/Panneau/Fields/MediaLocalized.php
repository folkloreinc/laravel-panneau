<?php

namespace Panneau\Fields;

class MediaLocalized extends UploadLocalized
{
    protected $endpoint = false;

    protected $withButton = false;

    protected $uploadOnly = false;

    protected $fieldClass = Media::class;
    protected $uploadFieldClass = Upload::class;

    public function field(string $locale)
    {
        $fieldClass = $this->uploadOnly ? $this->uploadFieldClass : $this->fieldClass;
        $field = new $fieldClass($locale);
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

    public function uploadOnly()
    {
        $this->uploadOnly = true;
        return $this;
    }
}
