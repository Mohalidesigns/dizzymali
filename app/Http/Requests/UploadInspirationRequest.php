<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UploadInspirationRequest extends FormRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        $max = (int) Setting::get('orders.max_inspiration_images', 5);

        return [
            // MIME is sniffed by Laravel's `image` and `mimetypes` rules, not
            // trusted from the extension. Everything is re-encoded downstream.
            'images' => ['required', 'array', 'max:'.$max],
            'images.*' => ['file', 'image', 'mimetypes:image/jpeg,image/png,image/webp,image/heic', 'max:25600'],
        ];
    }
}
