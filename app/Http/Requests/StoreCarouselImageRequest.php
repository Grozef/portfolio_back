<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarouselImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Toujours à true si tu gères l'auth via les routes
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:100',
            'image_url' => [
                'required',
                'string',
                'max:500',
                'regex:/^(https?:\/\/|\/carousel\/).*$/i'
            ],
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
