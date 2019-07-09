<?php

namespace Panneau\Resources;

use Panneau\Support\Resource;
use Panneau\Http\Requests\ResourceRequest;

class Users extends Resource
{
    protected $model = \Panneau\Contracts\User::class;

    protected $controller = \Panneau\Http\Controllers\UsersController::class;

    protected function forms()
    {
        return [
            'type' => 'normal',
            'fields' => [
                [
                    'name' => 'name',
                    'label' => trans('panneau::resources.users.fields.name_label'),
                    'type' => 'text'
                ],
                [
                    'name' => 'email',
                    'label' => trans('panneau::resources.users.fields.email_label'),
                    'type' => 'email'
                ],
                [
                    'name' => 'password',
                    'label' => trans('panneau::resources.users.fields.password_label'),
                    'type' => 'password'
                ],
                [
                    'name' => 'password_confirmation',
                    'label' => trans('panneau::resources.users.fields.password_confirmation_label'),
                    'type' => 'password'
                ]
            ],
        ];
    }

    public function getValidationFromRequest(ResourceRequest $request)
    {
        $isCreating = $request->isMethod('post');
        $id = $request->route(config('panneau.routes.parameters.id', 'id'));
        return [
            'rules' => [
                'name' => ['required'],
                'email' => $isCreating
                    ? ['required', 'email', 'unique:users,email']
                    : ['required', 'email', 'unique:users,email,'.$id.',id'],
                'password' => $isCreating ? ['required', 'confirmed'] : ['sometimes', 'required', 'confirmed'],
            ],
        ];
    }

    protected function lists()
    {
        return [
            'type' => 'table',
            'cols' => [
                [
                    'id' => 'id',
                    'path' => 'id',
                    'label' => 'ID',
                    'width' => 50
                ],
                [
                    'id' => 'name',
                    'path' => 'name',
                    'label' => 'Name'
                ],
                [
                    'id' => 'actions',
                    'type' => 'actions'
                ]
            ]
        ];
    }

    protected function messages()
    {
        return trans('panneau::resources.users');
    }
}
