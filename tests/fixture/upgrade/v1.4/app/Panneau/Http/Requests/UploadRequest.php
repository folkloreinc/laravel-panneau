<?php

namespace App\Panneau\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadRequest extends FormRequest
{
    public function rules()
    {
        return ['file' => 'required|file'];
    }
}
