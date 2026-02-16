<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Http\Resources\BookResource;
use App\Services\GoogleBooksService;
use App\Services\OpenLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    private OpenLibraryService $openLibrary;
    private GoogleBooksService $googleBooks;

    public function __construct(
        OpenLibraryService $openLibrary,
        GoogleBooksService $googleBooks
    ) {
        $this->openLibrary = $openLibrary;
        $this->googleBooks = $googleBooks;
    }

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
            'data' => BookResource::collection($books),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    public function show(Book $book): JsonResponse
    {
        $this->ensureCache($book);

        return response()->json([
            'success' => true,
            'data' => new BookResource($book),
        ]);
    }

    public function featured(): JsonResponse
    {
        $books = Book::featured()->ordered()->limit(6)->get();
        $books->each(fn($book) => $this->ensureCache($book));

        return response()->json([
            'success' => true,
            'data' => BookResource::collection($books),
        ]);
    }

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
                $title = $cachedData['title'];
                $author = $cachedData['author'];
                $coverUrl = $cachedData['cover_url'];
            } elseif (!$title) {
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
            'data' => new BookResource($book),
            'source' => $cachedData['source'] ?? 'manual',
        ], 201);
    }

    public function update(UpdateBookRequest $request, Book $book): JsonResponse
    {
        $book->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new BookResource($book->fresh()),
        ]);
    }

    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        return response()->json([
            'success' => true,
            'message' => 'Book deleted successfully',
        ]);
    }

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
            'data' => new BookResource($book->fresh()),
            'cache_updated' => (bool) $cachedData,
            'source' => $cachedData['source'] ?? null,
        ]);
    }

    private function fetchBookDataFromProviders(string $isbn): ?array
    {
        $data = $this->openLibrary->getBookByIsbn($isbn);

        if ($data) {
            $data['source'] = 'openlibrary';
            return $data;
        }

        $data = $this->googleBooks->getBookByIsbn($isbn);

        if ($data) {
            $data['source'] = 'google_books';
            return $data;
        }

        return null;
    }

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
