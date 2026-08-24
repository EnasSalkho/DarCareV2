<?php
// app/Modules/Auth/Http/Requests/RegisterProviderRequest.php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterProviderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:100'],
            'phone'                 => ['required', 'numeric', 'string', 'unique:providers,phone'],
            'email'                 => ['required', 'email', 'unique:providers,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'years_of_experience'   => ['required', 'integer', 'min:0'],
            'bio'                   => ['nullable', 'string', 'max:1000'],
            'profile_image'         => ['required', 'image', 'max:2048'],
            'category_ids'          => ['required', 'array'],
            'category_ids.*'        => ['integer', 'exists:categories,id'],
            'address.latitude'      => ['required', 'numeric', 'between:-90,90'],
            'address.longitude'     => ['required', 'numeric', 'between:-180,180'],
            'address.label'         => ['nullable', 'string', 'in:home,work,other'],
        ];
    }
}
