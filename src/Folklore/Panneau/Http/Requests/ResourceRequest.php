<?php

namespace Folklore\Panneau\Http\Requests;

use \Exception;
use Illuminate\Foundation\Http\FormRequest;

class ResourceRequest extends FormRequest
{
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
        return array_get($validation, 'rules', []);
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        $validation = $this->getResourceValidation();
        return array_get($validation, 'messages', []);
    }

    /**
     * Get the custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        $validation = $this->getResourceValidation();
        return array_get($validation, 'attributes', []);
    }

    protected function getResourceValidation()
    {
        $resource = $this->get('panneau.resource');
        if (is_null($resource)) {
            throw new Exception('Missing panneau.resource request property; is the "panneau.resource" middleware present on the route ?');
        }
        $validation = $resource->getValidationFromRequest($this);
        if ($resource->getType() === 'typed') {
            $type = $this->get('type', array_get($resource->getTypes(), '0.id'));
            return array_get($validation, $type, $validation);
        }
        return $validation;
    }
}
