<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Note what is NOT here: no price, no total, no yardage cost. The client tells
 * us which garment, which cloth and where to send it. The server decides what
 * that costs.
 */
class SaveDraftOrderRequest extends FormRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'wizard_step' => ['sometimes', 'integer', 'between:1,6'],
            'garment_type_id' => ['sometimes', 'integer', Rule::exists('garment_types', 'id')->where('is_active', true)],
            'fabric_variant_id' => ['sometimes', 'nullable', 'integer', Rule::exists('fabric_variants', 'id')->where('is_active', true)],
            'measurement_profile_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('measurement_profiles', 'id')->where('user_id', $userId),
            ],
            'quantity' => ['sometimes', 'integer', 'between:1,20'],
            'extra_yards' => ['sometimes', 'numeric', 'between:0,20'],
            'style_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'option_ids' => ['sometimes', 'array', 'max:20'],
            'option_ids.*' => ['integer', Rule::exists('garment_options', 'id')->where('is_active', true)],
            'service_level' => ['sometimes', Rule::in(['standard', 'express'])],
            'currency_code' => ['sometimes', Rule::exists('currencies', 'code')->where('is_active', true)],
            'shipping_address_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('addresses', 'id')->where('user_id', $userId),
            ],
            'customer_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'measurement_profile_id.exists' => 'That measurement profile does not belong to you.',
            'shipping_address_id.exists' => 'That address does not belong to you.',
        ];
    }
}
