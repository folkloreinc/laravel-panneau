<?php

namespace Folklore\Panneau\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResourceUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $validation = $this->getResourceValidation();
        return array_get($validation, 'update.rules', []);
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function message()
    {
        $validation = $this->getResourceValidation();
        return array_get($validation, 'update.messages', []);
    }
}
