<?php

namespace Folklore\Panneau\Http\Requests;

use Folklore\Panneau\Http\Requests\ResourceRequest;

class ResourceStoreRequest extends ResourceRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $validation = $this->getResourceValidation();
        return array_get($validation, 'store.rules', []);
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        $validation = $this->getResourceValidation();
        return array_get($validation, 'store.messages', []);
    }
}
