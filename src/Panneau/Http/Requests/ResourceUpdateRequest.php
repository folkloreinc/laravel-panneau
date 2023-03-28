<?php

namespace Panneau\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResourceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $user = $this->user();
        return !is_null($user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $resource = $this->resource();
        $fields = $resource->fields();
        $baseRules = collect($fields)
            ->reduce(function ($acc, $field) {
                return array_merge($acc, $field->getRulesFromRequest($this, $acc));
            }, [])
            ->toArray();
        return $baseRules;
    }
}
