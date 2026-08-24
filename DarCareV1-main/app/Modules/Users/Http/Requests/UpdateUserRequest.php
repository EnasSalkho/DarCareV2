<?php
// app/Modules/Users/Http/Requests/UpdateUserRequest.php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $userId = $this->user()->id;
        return [
            'name'          => ['sometimes', 'string', 'max:100'],
            'phone'         => ['sometimes', 'string', "unique:users,phone,{$userId}"],
            'profile_image' => ['sometimes', 'image', 'max:2048'],
        ];
    }
}
