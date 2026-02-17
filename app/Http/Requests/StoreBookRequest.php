<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Protégé par le middleware auth dans les routes
    }

    public function rules(): array
    {
        return [
            'isbn' => [
                'nullable',
                'string',
                'max:17',  // Pour inclure les tirets
                'unique:books,isbn',
                'regex:/^(?:\d{10}|\d{13}|(?:\d{1,5}-)+\d{1,5})$/'  // Format flexible
            ],
            // Le titre est requis si l'ISBN est absent
            'title' => 'required_without:isbn|nullable|string|max:255',
            'author' => 'nullable|string|max:255',
            'genre' => 'nullable|string|max:255',
            'cover_url' => [
                'nullable',
                'url',
                'max:500',
                // Sanitization : On n'accepte que les domaines de confiance
                'regex:/^(https?:\/\/(covers\.openlibrary\.org|books\.google\.com))/i'
            ],
            'status' => 'in:read,reading,to-read',
            'rating' => 'nullable|integer|min:1|max:5',
            'review' => 'nullable|string|max:5000',
            'is_featured' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'isbn.regex' => 'Format ISBN invalide (10 ou 13 chiffres attendus).',
            'isbn.unique' => 'Ce livre existe déjà dans votre bibliothèque.',
            'cover_url.regex' => 'L\'URL de la couverture doit provenir d\'une source autorisée.',
        ];
    }
}
