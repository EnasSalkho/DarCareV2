<?php
// app/Modules/Chat/Http/Requests/SendMessageRequest.php

namespace App\Modules\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body') && is_string($this->body)) {
            $this->merge(['body' => trim($this->body)]);
        }
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'reply_to_message_id' => ['nullable', 'integer', 'exists:messages,id'],
            'client_message_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
