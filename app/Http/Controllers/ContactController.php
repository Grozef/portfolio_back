<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    /**
     * Enregistre un nouveau message de contact.
     * * @param StoreContactRequest $request
     * @return JsonResponse
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        // STRATÉGIE HONEYPOT :
        // Si le champ 'website' est rempli, c'est un bot (car ce champ est masqué pour l'humain).
        // On retourne un succès (201) pour ne pas donner d'indice au bot,
        // mais on n'enregistre RIEN en base de données.
        if ($request->filled('website')) {
            Log::info("Spam détecté et bloqué : " . $request->email);

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
            ], 201);
        }

        // Si on arrive ici, c'est un humain.
        // On utilise $request->validated() qui exclut le champ 'website'.
        $message = ContactMessage::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $message,
        ], 201);
    }
}
