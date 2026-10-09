<?php

namespace Panneau\Http\Requests\Concerns;

use Panneau\Support\Field;

trait PreparesFieldsValues
{
    /**
     * Let the resource fields prepare their value before validation (ex: convert dates
     * to the app timezone).
     */
    protected function prepareForValidation()
    {
        $resource = $this->resource();
        if (is_null($resource)) {
            return;
        }
        $this->replace(Field::prepareFieldsRequestData($resource->fields(), $this->all()));
    }
}
