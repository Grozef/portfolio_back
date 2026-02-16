<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // L'autorisation est gérée par le middleware 'auth' et 'admin'
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120', // 5MB
                'dimensions:min_width=400,min_height=300,max_width=5000,max_height=5000',
            ],
        ];
    }
}
