<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarouselImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'      => 'nullable|string|max:255',
            'image_url'  => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/storage\/).*$/i'],
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ];
    }
}
