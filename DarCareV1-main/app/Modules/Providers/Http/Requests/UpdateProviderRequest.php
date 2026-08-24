<?php
// app/Modules/Providers/Http/Requests/UpdateProviderRequest.php

namespace App\Modules\Providers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $providerId = $this->user()->id;
        return [
            'name'                => ['sometimes', 'string', 'max:100'],
            'phone'               => ['sometimes', 'string', "unique:providers,phone,{$providerId}"],
            'bio'                 => ['sometimes', 'nullable', 'string', 'max:1000'],
            'years_of_experience' => ['sometimes', 'integer', 'min:0'],
            'profile_image'       => ['sometimes', 'image', 'max:2048'],
        ];
    }
}
