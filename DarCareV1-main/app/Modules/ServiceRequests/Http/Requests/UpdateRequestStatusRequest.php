<?php
// app/Modules/ServiceRequests/Http/Requests/UpdateRequestStatusRequest.php

namespace App\Modules\ServiceRequests\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:accepted,rejected,delayed,completed,cancelled'],
            'scheduled_at' => ['nullable', 'required_if:status,delayed', 'date', 'after:now'],
        ];
    }
}
