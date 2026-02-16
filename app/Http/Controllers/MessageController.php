<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Http\Resources\ContactMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $messages = ContactMessage::orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ContactMessageResource::collection($messages),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'per_page'     => $messages->perPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    public function show(ContactMessage $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ContactMessageResource($message),
        ]);
    }

    public function markAsRead(ContactMessage $message): JsonResponse
    {
        $message->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => new ContactMessageResource($message),
        ]);
    }

    public function markAsUnread(ContactMessage $message): JsonResponse
    {
        $message->update(['read_at' => null]);

        return response()->json([
            'success' => true,
            'data' => new ContactMessageResource($message),
        ]);
    }

    public function destroy(ContactMessage $message): JsonResponse
    {
        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully',
        ]);
    }
}
