<?php

namespace App\Http\Controllers;

use App\Models\CarouselImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CarouselImageController extends Controller
{
    /**
     * List all active carousel images (public).
     *
     * IMPROVED: Added file existence check to prevent returning broken image URLs
     */
    public function index(Request $request): JsonResponse
    {
        $query = CarouselImage::query()->ordered();

        // If not authenticated, only show active images
        if (!$request->user()) {
            $query->active();
        }

        $images = $query->get();

        // Filter out images where file doesn't exist (for local files)
        $validImages = $images->filter(function ($image) {
            if (Str::startsWith($image->image_url, '/carousel/')) {
                $filePath = public_path($image->image_url);
                if (!file_exists($filePath)) {
                    Log::warning("Carousel image file not found: {$image->image_url}", [
                        'image_id' => $image->id,
                        'expected_path' => $filePath
                    ]);
                    return false;
                }
            }
            return true;
        })->values(); // Reset array keys

        return response()->json([
            'success' => true,
            'data' => $validImages,
        ]);
    }

    /**
     * Show a specific carousel image.
     */
    public function show(CarouselImage $carouselImage): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $carouselImage,
        ]);
    }

    /**
     * Upload an image DIRECTLY to public/carousel.
     * FIXED: Windows upload bug with move() - now uses file_put_contents
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            ]);

            $file = $request->file('image');

            // Verify file exists and is valid
            if (!$file->isValid()) {
                throw new \Exception('Invalid file upload');
            }

            // Get file content BEFORE any operations
            $fileContent = file_get_contents($file->getRealPath());
            $extension = $file->getClientOriginalExtension();

            // Generate unique filename
            $filename = Str::random(40) . '.' . $extension;

            // Create public/carousel directory if it doesn't exist
            $carouselPath = public_path('carousel');
            if (!is_dir($carouselPath)) {
                if (!mkdir($carouselPath, 0755, true)) {
                    throw new \Exception("Failed to create carousel directory");
                }
            }

            // Full path for the new file
            $destinationPath = $carouselPath . DIRECTORY_SEPARATOR . $filename;

            // Write file content directly (more reliable than move on Windows)
            if (file_put_contents($destinationPath, $fileContent) === false) {
                throw new \Exception("Failed to save file");
            }

            // Verify file was written
            if (!file_exists($destinationPath)) {
                throw new \Exception("File was not saved correctly");
            }

            // Build accessible URL
            $url = '/carousel/' . $filename;

            Log::info("Image uploaded successfully", [
                'filename' => $filename,
                'url' => $url,
                'size' => strlen($fileContent),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url,
                    'filename' => $filename,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions to show proper errors
            throw $e;
        } catch (\Exception $e) {
            Log::error("Image upload failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Add a new image to carousel.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'image_url' => [
                    'required',
                    'string',
                    'max:500',
                    'regex:/^(https?:\/\/|\/carousel\/).*$/i'
                ],
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ], [
                'image_url.regex' => 'Image URL must be either a valid URL (http/https) or a /carousel/ path.',
                'image_url.required' => 'Image URL is required.',
            ]);

            $image = CarouselImage::create($validated);

            Log::info("Carousel image created", ['image_id' => $image->id]);

            return response()->json([
                'success' => true,
                'data' => $image,
            ], 201);
        } catch (\Exception $e) {
            Log::error("Failed to create carousel image", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create carousel image',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update a carousel image.
     */
    public function update(Request $request, CarouselImage $carouselImage): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'image_url' => [
                    'nullable',
                    'string',
                    'max:500',
                    'regex:/^(https?:\/\/|\/carousel\/).*$/i'
                ],
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ], [
                'image_url.regex' => 'Image URL must be either a valid URL (http/https) or a /carousel/ path.',
            ]);

            $carouselImage->update($validated);

            Log::info("Carousel image updated", ['image_id' => $carouselImage->id]);

            return response()->json([
                'success' => true,
                'data' => $carouselImage->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update carousel image", [
                'image_id' => $carouselImage->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update carousel image',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete a carousel image.
     * Also removes the file from public/carousel if it exists.
     */
    public function destroy(CarouselImage $carouselImage): JsonResponse
    {
        try {
            $imageUrl = $carouselImage->image_url;

            // Delete the file if it's in public/carousel
            if (Str::startsWith($imageUrl, '/carousel/')) {
                $filename = basename($imageUrl);
                $filePath = public_path('carousel' . DIRECTORY_SEPARATOR . $filename);

                if (file_exists($filePath)) {
                    if (!unlink($filePath)) {
                        Log::warning("Failed to delete file", ['path' => $filePath]);
                    } else {
                        Log::info("Deleted carousel image file", ['path' => $filePath]);
                    }
                }
            }

            $carouselImage->delete();

            Log::info("Carousel image deleted", ['image_url' => $imageUrl]);

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to delete carousel image", [
                'image_id' => $carouselImage->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete carousel image',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Reorder carousel images.
     * Expects an array of IDs in the desired order.
     */
    public function reorder(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order' => 'required|array|min:1',
                'order.*' => 'integer|exists:carousel_images,id',
            ]);

            foreach ($validated['order'] as $index => $imageId) {
                CarouselImage::where('id', $imageId)->update(['sort_order' => $index]);
            }

            $images = CarouselImage::ordered()->get();

            Log::info("Carousel images reordered", ['count' => count($validated['order'])]);

            return response()->json([
                'success' => true,
                'data' => $images,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to reorder carousel images", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder carousel images',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}