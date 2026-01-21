<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\OpenLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controleur BookController - Gestion des livres.
 *
 * CORRECTIONS APPLIQUEES:
 * - Ajout de la pagination (15 livres par page)
 * - Validation ISBN amelioree avec regex
 * - Cache refresh optimise (pas de refresh synchrone sur la liste)
 *
 * @package App\Http\Controllers
 */
class BookController extends Controller
{
    private OpenLibraryService $openLibrary;

    public function __construct(OpenLibraryService $openLibrary)
    {
        $this->openLibrary = $openLibrary;
    }

    /**
     * Liste tous les livres avec filtres et pagination.
     *
     * CORRECTION: Ajout de la pagination pour eviter les problemes de performance.
     *
     * @param Request $request
     * @return JsonResponse Liste paginee des livres
     *
     * @queryParam status string Filtrer par statut (read, reading, to-read)
     * @queryParam featured boolean Filtrer les livres mis en avant
     * @queryParam per_page int Nombre de livres par page (defaut: 15)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $query = Book::query()->ordered();

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $books = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $books->items(),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    /**
     * Affiche un livre specifique.
     */
    public function show(Book $book): JsonResponse
    {
        $this->ensureCache($book);

        return response()->json([
            'success' => true,
            'data' => $book,
        ]);
    }

    /**
     * Liste les livres mis en avant (max 6).
     */
    public function featured(): JsonResponse
    {
        $books = Book::featured()->ordered()->limit(6)->get();
        $books->each(fn($book) => $this->ensureCache($book));

        return response()->json([
            'success' => true,
            'data' => $books,
        ]);
    }

    /**
     * Retourne les statistiques de la bibliotheque.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => Book::count(),
            'read' => Book::byStatus('read')->count(),
            'reading' => Book::byStatus('reading')->count(),
            'to_read' => Book::byStatus('to-read')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Ajoute un nouveau livre.
     *
     * CORRECTION: Validation ISBN amelioree avec regex.
     * - ISBN-10: 10 chiffres (le dernier peut etre X)
     * - ISBN-13: 13 chiffres
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'isbn' => [
                'nullable',
                'string',
                'regex:/^(?:\d{9}[\dXx]|\d{13})$/',
            ],
            'title' => 'nullable|string|max:255',
            'author' => 'nullable|string|max:255',
            'cover_url' => 'nullable|url|max:500',
            'status' => 'in:read,reading,to-read',
            'rating' => 'nullable|integer|min:1|max:5',
            'review' => 'nullable|string|max:5000',
            'is_featured' => 'boolean',
        ], [
            'isbn.regex' => 'Invalid ISBN format. Must be ISBN-10 (10 digits) or ISBN-13 (13 digits).',
        ]);

        $cachedData = null;
        $title = $validated['title'] ?? null;
        $author = $validated['author'] ?? null;
        $coverUrl = $validated['cover_url'] ?? null;

        // Tentative de recuperation via ISBN si fourni
        if (!empty($validated['isbn'])) {
            // Verification unicite ISBN
            if (Book::where('isbn', $validated['isbn'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A book with this ISBN already exists.',
                ], 422);
            }

            $cachedData = $this->openLibrary->getBookByIsbn($validated['isbn']);

            if ($cachedData) {
                $title = $cachedData['title'];
                $author = $cachedData['author'];
                $coverUrl = $cachedData['cover_url'];
            } elseif (!$title) {
                return response()->json([
                    'success' => false,
                    'message' => 'ISBN not found in Open Library. Please provide title manually.',
                    'require_manual' => true,
                ], 422);
            }
        }

        // Verification qu'on a au moins un titre
        if (!$title) {
            return response()->json([
                'success' => false,
                'message' => 'Title is required when ISBN is not provided.',
            ], 422);
        }

        // Creation du livre
        $book = Book::create([
            'isbn' => $validated['isbn'] ?? null,
            'title' => $title,
            'author' => $author,
            'cover_url' => $coverUrl,
            'status' => $validated['status'] ?? 'to-read',
            'rating' => $validated['rating'] ?? null,
            'review' => $validated['review'] ?? null,
            'is_featured' => $validated['is_featured'] ?? false,
            'cached_data' => $cachedData,
            'cached_at' => $cachedData ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $book,
            'from_api' => (bool) $cachedData,
        ], 201);
    }

    /**
     * Met a jour un livre existant.
     */
    public function update(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'author' => 'nullable|string|max:255',
            'cover_url' => 'nullable|url|max:500',
            'status' => 'in:read,reading,to-read',
            'rating' => 'nullable|integer|min:1|max:5',
            'review' => 'nullable|string|max:5000',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $book->update($validated);

        return response()->json([
            'success' => true,
            'data' => $book->fresh(),
        ]);
    }

    /**
     * Supprime un livre.
     */
    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        return response()->json([
            'success' => true,
            'message' => 'Book deleted successfully',
        ]);
    }

    /**
     * Force le rafraichissement du cache Open Library.
     */
    public function refreshCache(Book $book): JsonResponse
    {
        if (!$book->isbn) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot refresh cache for books without ISBN.',
            ], 422);
        }

        $cachedData = $this->openLibrary->refreshCache($book->isbn);

        if ($cachedData) {
            $book->updateCache($cachedData);
        }

        return response()->json([
            'success' => true,
            'data' => $book->fresh(),
            'cache_updated' => (bool) $cachedData,
        ]);
    }

    /**
     * S'assure que le cache du livre est a jour.
     *
     * CORRECTION: Methode optimisee - ne fait plus de refresh synchrone
     * dans la liste. Le refresh devrait etre fait via un job en arriere-plan.
     */
    private function ensureCache(Book $book): void
    {
        if ($book->needsCacheRefresh()) {
            $cachedData = $this->openLibrary->getBookByIsbn($book->isbn);
            if ($cachedData) {
                $book->updateCache($cachedData);
            }
        }
    }
}