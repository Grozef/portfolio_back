<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Services\GoogleBooksService;
use App\Services\OpenLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Book Controller - Book collection management
 *
 * IMPROVEMENTS:
 * - Multi-provider ISBN search (OpenLibrary + Google Books)
 * - Better French book coverage
 * - Automatic provider fallback
 * - Source tracking for debugging
 *
 * @package App\Http\Controllers
 */
class BookController extends Controller
{
    private OpenLibraryService $openLibrary;
    private GoogleBooksService $googleBooks;

    /**
     * Constructor with dependency injection
     */
    public function __construct(
        OpenLibraryService $openLibrary,
        GoogleBooksService $googleBooks
    ) {
        $this->openLibrary = $openLibrary;
        $this->googleBooks = $googleBooks;
    }

    /**
     * List all books with filters and pagination
     *
     * @param Request $request
     * @return JsonResponse Paginated books list
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
     * Show specific book
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
     * List featured books (max 6)
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
     * Get library statistics
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
 * Create new book
 */
public function store(StoreBookRequest $request): JsonResponse
{
    $validated = $request->validated();

    $cachedData = null;
    $title = $validated['title'] ?? null;
    $author = $validated['author'] ?? null;
    $coverUrl = $validated['cover_url'] ?? null;

    if (!empty($validated['isbn'])) {
        $cachedData = $this->fetchBookDataFromProviders($validated['isbn']);

        if ($cachedData) {
            // Priorité aux données API si elles existent
            $title = $cachedData['title'];
            $author = $cachedData['author'];
            $coverUrl = $cachedData['cover_url'];
        } elseif (!$title) {
            // Si pas d'API et pas de titre manuel
            return response()->json([
                'success' => false,
                'message' => 'Livre introuvable via ISBN. Veuillez saisir le titre manuellement.',
                'require_manual' => true
            ], 422);
        }
    }

    $book = Book::create(array_merge($validated, [
        'title' => $title,
        'author' => $author,
        'cover_url' => $coverUrl,
        'cached_data' => $cachedData,
        'cached_at' => $cachedData ? now() : null,
    ]));

    return response()->json([
        'success' => true,
        'data' => $book,
        'source' => $cachedData['source'] ?? 'manual',
    ], 201);
}

/**
 * Update existing book
 */
public function update(UpdateBookRequest $request, Book $book): JsonResponse
{
    $book->update($request->validated());

    return response()->json([
        'success' => true,
        'data' => $book->fresh(),
    ]);
}

    /**
     * Delete book
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
     * Force cache refresh from all providers
     *
     * Tries OpenLibrary first, then Google Books
     */
    public function refreshCache(Book $book): JsonResponse
    {
        if (!$book->isbn) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot refresh cache for books without ISBN.',
            ], 422);
        }

        $cachedData = $this->fetchBookDataFromProviders($book->isbn);

        if ($cachedData) {
            $book->updateCache($cachedData);
        }

        return response()->json([
            'success' => true,
            'data' => $book->fresh(),
            'cache_updated' => (bool) $cachedData,
            'source' => $cachedData['source'] ?? null,
        ]);
    }

    /**
     * Fetch book data from multiple providers with fallback
     *
     * Provider priority:
     * 1. OpenLibrary (free, no rate limits)
     * 2. Google Books (better French and international coverage)
     *
     * @param string $isbn ISBN to search
     * @return array|null Book data with 'source' key, or null if not found
     */
    private function fetchBookDataFromProviders(string $isbn): ?array
    {
        // Try OpenLibrary first (free, no limits)
        $data = $this->openLibrary->getBookByIsbn($isbn);

        if ($data) {
            $data['source'] = 'openlibrary';
            return $data;
        }

        // Fallback to Google Books (better coverage for French books)
        $data = $this->googleBooks->getBookByIsbn($isbn);

        if ($data) {
            $data['source'] = 'google_books';
            return $data;
        }

        return null;
    }

    /**
     * Ensure cache is up to date
     *
     * Checks if cache needs refresh and updates if necessary.
     * Uses multi-provider strategy for best results.
     *
     * @param Book $book Book to check
     * @return void
     */
    private function ensureCache(Book $book): void
    {
        if ($book->needsCacheRefresh()) {
            $cachedData = $this->fetchBookDataFromProviders($book->isbn);
            if ($cachedData) {
                $book->updateCache($cachedData);
            }
        }
    }
}
