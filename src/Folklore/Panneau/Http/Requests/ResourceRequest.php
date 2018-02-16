<?php

namespace Folklore\Panneau\Http\Requests;

use \Exception;
use Illuminate\Foundation\Http\FormRequest;

class ResourceRequest extends FormRequest
{
    protected $validationPrefix;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $validation = $this->getResourceValidation();
        return array_get($validation, $this->validationPrefix.'.rules', []);
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        $validation = $this->getResourceValidation();
        return array_get($validation, $this->validationPrefix.'.messages', []);
    }

    /**
     * Get the custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        $validation = $this->getResourceValidation();
        return array_get($validation, $this->validationPrefix.'.attributes', []);
    }

    protected function getResourceValidation()
    {
        $resource = $this->get('panneau.resource');
        if (is_null($resource)) {
            throw new Exception('Missing panneau.resource request property; is the "panneau.middlewares.inject_resource" middleware present on the route ?');
        }
        $validation = $resource->getValidationFromRequest($this);
        return $validation;
    }
}
