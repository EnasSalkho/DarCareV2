<?php

namespace App\Modules\Dashboard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MonthlyRequestStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('year') || $this->input('year') === null || $this->input('year') === '') {
            $this->merge(['year' => (int) now(config('app.timezone'))->year]);
        }
    }

    public function rules(): array
    {
        $currentYear = (int) now(config('app.timezone'))->year;

        return [
            'year' => ['required', 'integer', 'min:2000', 'max:'.($currentYear + 1)],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')],
            'provider_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('providers', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function year(): int
    {
        return (int) $this->validated('year');
    }

    /**
     * @return array{category_id?: int, provider_id?: int}
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $filters = [];

        if (! empty($validated['category_id'])) {
            $filters['category_id'] = (int) $validated['category_id'];
        }

        if (! empty($validated['provider_id'])) {
            $filters['provider_id'] = (int) $validated['provider_id'];
        }

        return $filters;
    }
}
