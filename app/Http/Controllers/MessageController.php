<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Liste tous les messages de contact.
     *
     * @return JsonResponse Liste des messages
     */
    public function index(): JsonResponse
    {
        $messages = ContactMessage::orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $messages,
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