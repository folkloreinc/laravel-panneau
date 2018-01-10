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

    protected function getResourceValidation()
    {
        if (!isset($this->panneauResource)) {
            throw new Exception('Missing panneauResource request property; is the "panneau.middlewares.injectresource" middleware added to route?');
        }
        $validation = $this->panneauResource->getValidation();
        return $validation;
    }
}
