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
        $resource = $this->get('panneau.resource');
        if (is_null($resource)) {
            throw new Exception('Missing panneauResource request property; is the "panneau.middlewares.injectresource" middleware added to route?');
        }
        $validation = $resource->getValidation();
        return $validation;
    }
}
