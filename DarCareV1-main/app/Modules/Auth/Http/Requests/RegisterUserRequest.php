<?php
// app/Modules/Auth/Http/Requests/RegisterUserRequest.php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:100'],
            'phone'                 => ['required', 'numeric', 'string', 'unique:users,phone'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'city_id'               => ['required'],
            'address.latitude'      => ['required', 'numeric', 'between:-90,90'],
            'address.longitude'     => ['required', 'numeric', 'between:-180,180'],
            'address.label'         => ['nullable', 'string', 'in:home,work,other'],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
