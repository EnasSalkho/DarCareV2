<?php
// app/Modules/Ratings/Http/Requests/StoreRatingRequest.php

namespace App\Modules\Ratings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRatingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'provider_id'        => ['required', 'integer', 'exists:providers,id'],
            'service_request_id' => ['required', 'integer', 'exists:service_requests,id'],
            'rating'             => ['required', 'integer', 'between:1,5'],
            'comment'            => ['nullable', 'string', 'max:1000'],
        ];
    }
}
