<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarouselImageRequest;
use App\Http\Requests\UploadImageRequest;
use App\Models\CarouselImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CarouselImageController extends Controller
{
    /**
     * Liste toutes les images actives (public).
     */
    public function index(Request $request): JsonResponse
    {
        $query = CarouselImage::query()->ordered();

        if (!$request->user()) {
            $query->active();
        }

        $images = $query->get();

        $validImages = $images->filter(function ($image) {
            if (Str::startsWith($image->image_url, '/carousel/')) {
                $filePath = public_path($image->image_url);
                if (!file_exists($filePath)) {
                    Log::warning("Carousel image file not found: {$image->image_url}", ['image_id' => $image->id]);
                    return false;
                }
            }
            return true;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $validImages,
        ]);
    }

    /**
     * Upload un fichier physique (Sécurisé par UploadImageRequest).
     */
    public function upload(UploadImageRequest $request): JsonResponse
    {
        try {
            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension();
            $filename = Str::random(40) . '.' . $extension;

            $carouselPath = public_path('carousel');
            if (!is_dir($carouselPath)) {
                mkdir($carouselPath, 0755, true);
            }

            $destinationPath = $carouselPath . DIRECTORY_SEPARATOR . $filename;

            // Utilisation de file_put_contents pour la compatibilité Windows/Laragon
            if (file_put_contents($destinationPath, file_get_contents($file->getRealPath())) === false) {
                throw new \Exception("Failed to save file to disk");
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => '/carousel/' . $filename,
                    'filename' => $filename,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Image upload failed", ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Upload failed'], 500);
        }
    }

    /**
     * Enregistre l'entrée en base (Sécurisé par StoreCarouselImageRequest).
     */
    public function store(StoreCarouselImageRequest $request): JsonResponse
    {
        $image = CarouselImage::create($request->validated());

        Log::info("Carousel image created", ['image_id' => $image->id]);

        return response()->json([
            'success' => true,
            'data' => $image,
        ], 201);
    }

    /**
     * Met à jour une image.
     */
    public function update(StoreCarouselImageRequest $request, CarouselImage $carouselImage): JsonResponse
    {
        $carouselImage->update($request->validated());

        Log::info("Carousel image updated", ['image_id' => $carouselImage->id]);

        return response()->json([
            'success' => true,
            'data' => $carouselImage->fresh(),
        ]);
    }

    /**
     * Supprime une image et son fichier physique.
     */
    public function destroy(CarouselImage $carouselImage): JsonResponse
    {
        $imageUrl = $carouselImage->image_url;

        if (Str::startsWith($imageUrl, '/carousel/')) {
            $filePath = public_path($imageUrl);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $carouselImage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully',
        ]);
    }

    /**
     * Réordonne les images (Validation inline car spécifique).
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array|min:1',
            'order.*' => 'integer|exists:carousel_images,id',
        ]);

        foreach ($validated['order'] as $index => $imageId) {
            CarouselImage::where('id', $imageId)->update(['sort_order' => $index]);
        }

        return response()->json([
            'success' => true,
            'data' => CarouselImage::ordered()->get(),
        ]);
    }
}
