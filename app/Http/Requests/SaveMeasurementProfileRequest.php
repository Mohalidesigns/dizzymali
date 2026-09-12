<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMeasurementProfileRequest extends FormRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'unit' => ['required', Rule::in(['in', 'cm'])],
            'source' => ['sometimes', Rule::in(['manual', 'uploaded', 'tailor_assisted'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
            'values' => ['required', 'array', 'min:1'],
            'values.*' => ['nullable', 'numeric', 'between:0,300'],
        ];
    }
}
