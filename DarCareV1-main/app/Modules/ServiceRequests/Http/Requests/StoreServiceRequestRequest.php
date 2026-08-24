<?php
// app/Modules/ServiceRequests/Http/Requests/StoreServiceRequestRequest.php

namespace App\Modules\ServiceRequests\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'provider_id'   => ['required', 'integer', 'exists:providers,id'],
            'category_id'   => ['required', 'integer', 'exists:categories,id'],
            'address_id'    => ['nullable', 'integer', 'exists:addresses,id'],
            'description'   => ['required', 'string', 'max:2000'],
            'urgency'       => ['nullable', 'in:urgent,normal'],
            'image'         => ['nullable', 'image', 'max:4096'],
            'temp_latitude' => ['nullable', 'required_with:temp_longitude', 'numeric'],
            'temp_longitude'=> ['nullable', 'required_with:temp_latitude', 'numeric'],
            'temp_label'    => ['nullable', 'string'],
        ];
    }
}
