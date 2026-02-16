<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|min:10|max:5000',
            // Le champ honeypot : doit être présent dans la requête (via le front) mais vide
            'website' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'message.min' => 'Votre message doit faire au moins 10 caractères.',
            'email.email' => 'Veuillez fournir une adresse email valide.',
        ];
    }
}
