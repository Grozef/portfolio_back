<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\OpenLibraryService;
use App\Services\GoogleBooksService;
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
     *
     * Tries to fetch book data from multiple providers:
     * 1. OpenLibrary (free, no limits)
     * 2. Google Books (better French coverage)
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
            'genre' => 'nullable|string|max:100',
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

        // Fetch from providers if ISBN provided
        if (!empty($validated['isbn'])) {
            // Check ISBN uniqueness
            if (Book::where('isbn', $validated['isbn'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A book with this ISBN already exists.',
                ], 422);
            }

            // Try to fetch from multiple providers
            $cachedData = $this->fetchBookDataFromProviders($validated['isbn']);

            if ($cachedData) {
                $title = $cachedData['title'];
                $author = $cachedData['author'];
                $coverUrl = $cachedData['cover_url'];
            } elseif (!$title) {
                return response()->json([
                    'success' => false,
                    'message' => 'ISBN not found in any provider (OpenLibrary, Google Books). Please provide title manually.',
                    'require_manual' => true,
                ], 422);
            }
        }

        // Require title
        if (!$title) {
            return response()->json([
                'success' => false,
                'message' => 'Title is required when ISBN is not provided.',
            ], 422);
        }

        // Create book
        $book = Book::create([
            'isbn' => $validated['isbn'] ?? null,
            'title' => $title,
            'author' => $author,
            'genre' => $validated['genre'] ?? null,
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
            'source' => $cachedData['source'] ?? null,
        ], 201);
    }

    /**
     * Update existing book
     */
    public function update(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'author' => 'nullable|string|max:255',
            'genre' => 'nullable|string|max:100',
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
