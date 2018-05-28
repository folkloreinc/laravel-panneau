<?php

namespace Folklore\Panneau\Http\Controllers;

class UsersController extends ResourceController
{
    protected function saveItem($item, $data, Request $request)
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }
        return parent::saveItem($item, $data, $request);
    }
}
