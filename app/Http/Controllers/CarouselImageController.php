<?php

namespace App\Http\Controllers;

use App\Models\CarouselImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarouselImageController extends Controller
{
    /**
     * Liste toutes les images actives du carrousel (public).
     */
    public function index(Request $request): JsonResponse
    {
        $query = CarouselImage::query()->ordered();

        // Si pas authentifié, seulement les images actives
        if (!$request->user()) {
            $query->active();
        }

        $images = $query->get();

        return response()->json([
            'success' => true,
            'data' => $images,
        ]);
    }

    /**
     * Affiche une image specifique.
     */
    public function show(CarouselImage $carouselImage): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $carouselImage,
        ]);
    }

    /**
     * Upload une image.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        $file = $request->file('image');

        // Generer un nom unique
        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();

        // Sauvegarder dans storage/app/public/carousel
        $path = $file->storeAs('carousel', $filename, 'public');

        // URL publique
        $url = Storage::url($path);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'filename' => $filename,
            ],
        ]);
    }

    /**
     * Ajoute une nouvelle image au carrousel.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'image_url' => 'required|string|max:500',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ]);

        $image = CarouselImage::create($validated);

        return response()->json([
            'success' => true,
            'data' => $image,
        ], 201);
    }

    /**
     * Met a jour une image du carrousel.
     */
    public function update(Request $request, CarouselImage $carouselImage): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'image_url' => 'string|max:500',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ]);

        $carouselImage->update($validated);

        return response()->json([
            'success' => true,
            'data' => $carouselImage->fresh(),
        ]);
    }

    /**
     * Supprime une image du carrousel.
     */
    public function destroy(CarouselImage $carouselImage): JsonResponse
    {
        // Supprimer le fichier si c'est une image uploadée
        if (Str::startsWith($carouselImage->image_url, '/storage/carousel/')) {
            $path = str_replace('/storage/', '', $carouselImage->image_url);
            Storage::disk('public')->delete($path);
        }

        $carouselImage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully',
        ]);
    }

    /**
     * Reordonne les images du carrousel.
     * Attend un tableau d'IDs dans l'ordre souhaite.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:carousel_images,id',
        ]);

        foreach ($validated['order'] as $index => $imageId) {
            CarouselImage::where('id', $imageId)->update(['sort_order' => $index]);
        }

        $images = CarouselImage::ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $images,
        ]);
    }
}