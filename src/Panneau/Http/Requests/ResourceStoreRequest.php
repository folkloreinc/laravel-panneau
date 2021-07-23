<?php

namespace Panneau\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResourceStoreRequest extends FormRequest
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
        $rules = collect($resource->fields())
            ->mapWithKeys(function ($field) {
                $name = $field->name();
                $required = $field->required();
                $fieldRules = $field->getRulesFromRequest($this);
                $allRules = $required ? array_merge(['required'], $fieldRules) : $fieldRules;
                return !is_null($allRules) && sizeof($allRules)
                    ? [
                        $name => $allRules,
                    ]
                    : [];
            })
            ->toArray();
        
        $customRules = collect($resource->fields())->reduce(function ($rules, $field) {
            $fieldRules = $field->getCustomRules($this);
            if(is_array($fieldRules)) {
                foreach($fieldRules as $name => $rule) {
                    $rules[$name] = $rule;
                }
            }
            return $rules;
        }, []);

        return $rules;
    }
}
