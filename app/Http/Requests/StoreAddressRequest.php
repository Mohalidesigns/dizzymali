<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:40'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'line_1' => ['required', 'string', 'max:180'],
            'line_2' => ['sometimes', 'nullable', 'string', 'max:180'],
            'city' => ['required', 'string', 'max:80'],
            'state_region' => ['sometimes', 'nullable', 'string', 'max:80'],
            'postcode' => ['sometimes', 'nullable', 'string', 'max:24'],
            'country_code' => ['required', 'string', 'size:2'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country_code')) {
            $this->merge(['country_code' => strtoupper((string) $this->input('country_code'))]);
        }
    }
}
