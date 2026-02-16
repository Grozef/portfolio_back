<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'isbn' => [
                'sometimes',
                'string',
                'regex:/^(?:\d{9}[\dXx]|\d{13})$/',
                Rule::unique('books')->ignore($this->book), // Ignore l'ID actuel
            ],
            'title' => 'sometimes|string|max:255',
            'status' => 'in:read,reading,to-read',
            'cover_url' => 'nullable|url|regex:/^(https?:\/\/(covers\.openlibrary\.org|books\.google\.com))/i',
            'is_featured' => 'boolean',
            'rating' => 'nullable|integer|min:0|max:5',
            'sort_order' => 'integer',
        ];
    }
}
