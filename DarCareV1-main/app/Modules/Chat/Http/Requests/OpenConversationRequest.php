<?php

namespace App\Modules\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
{
    return [

        'type' => ['required', Rule::in(['request', 'support_customer', 'support_provider', 'direct'])],
        
        'service_request_id' => [
            'required_if:type,request',
            'nullable',
            'integer',
            'exists:service_requests,id',
            'prohibited_unless:type,request',
        ],


        'receiver_id' => [
            'required_if:type,direct',
            'nullable',
            'integer',
        ],
    ];
}
}
