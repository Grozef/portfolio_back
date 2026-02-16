<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
// use Illuminate\Http\Request;

/**
 * Controleur MessageController - Gestion admin des messages.
 *
 * Permet aux admins de voir, lire et supprimer les messages de contact.
 *
 * @package App\Http\Controllers
 */
class MessageController extends Controller
{
/**
     * Liste les messages avec pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        // Utilisation de paginate au lieu de get
        $messages = ContactMessage::orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $messages->items(), // Uniquement les messages de la page
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'per_page'     => $messages->perPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    /**
     * Affiche un message specifique.
     *
     * @param ContactMessage $message
     * @return JsonResponse Detail du message
     */
    public function show(ContactMessage $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $message,
        ]);
    }

    /**
     * Marque un message comme lu.
     *
     * @param ContactMessage $message
     * @return JsonResponse Message mis a jour
     */
    public function markAsRead(ContactMessage $message): JsonResponse
    {
        $message->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => $message,
        ]);
    }

    /**
     * Marque un message comme non lu.
     *
     * @param ContactMessage $message
     * @return JsonResponse Message mis a jour
     */
    public function markAsUnread(ContactMessage $message): JsonResponse
    {
        $message->update(['read_at' => null]);

        return response()->json([
            'success' => true,
            'data' => $message,
        ]);
    }

    /**
     * Supprime un message.
     *
     * @param ContactMessage $message
     * @return JsonResponse Confirmation
     */
    public function destroy(ContactMessage $message): JsonResponse
    {
        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully',
        ]);
    }
}
