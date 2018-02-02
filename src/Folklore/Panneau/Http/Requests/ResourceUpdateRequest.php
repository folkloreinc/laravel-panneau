<?php

namespace Folklore\Panneau\Http\Requests;

use Folklore\Panneau\Http\Requests\ResourceRequest;

class ResourceUpdateRequest extends ResourceRequest
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
    public function messages()
    {
        $validation = $this->getResourceValidation();
        return array_get($validation, 'update.messages', []);
    }

    /**
     * Get the custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        // @TODO
        return [];
    }
}
