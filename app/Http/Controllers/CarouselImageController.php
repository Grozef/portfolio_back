<?php

namespace App\Http\Controllers;

use App\Http\Requests\{StoreCarouselImageRequest, UploadImageRequest};
use App\Models\CarouselImage;
use App\Http\Resources\CarouselImageResource;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Storage, Log, DB};

class CarouselImageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // On récupère les données. La cohérence fichier/DB se gère à l'upload/delete, pas ici.
        $images = CarouselImage::ordered()
            ->when(!$request->user(), fn($q) => $q->active())
            ->get();

        return response()->json([
            'success' => true,
            'data' => CarouselImageResource::collection($images),
        ]);
    }

    public function upload(UploadImageRequest $request): JsonResponse
    {
        try {
            // store() génère un nom unique et gère le dossier automatiquement
            $path = $request->file('image')->store('carousel', 'public');

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => Storage::url($path),
                    'filename' => basename($path),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Upload failed: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Upload failed'], 500);
        }
    }

    public function store(StoreCarouselImageRequest $request): JsonResponse
    {
        $image = CarouselImage::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CarouselImageResource($image),
        ], 201);
    }

    public function destroy(CarouselImage $carouselImage): JsonResponse
    {
        // On extrait le chemin relatif depuis l'URL stockée
        $relativeDiskPath = str_replace('/storage/', '', $carouselImage->image_url);

        if (Storage::disk('public')->exists($relativeDiskPath)) {
            Storage::disk('public')->delete($relativeDiskPath);
        }

        $carouselImage->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:carousel_images,id',
        ]);

        // Utilisation d'une transaction pour éviter les états instables
        DB::transaction(function () use ($request) {
            foreach ($request->order as $index => $imageId) {
                CarouselImage::where('id', $imageId)->update(['sort_order' => $index]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => CarouselImageResource::collection(CarouselImage::ordered()->get()),
        ]);
    }
}
