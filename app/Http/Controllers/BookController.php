<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\OpenLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controleur BookController - Gestion des livres.
 * 
 * Fournit les endpoints CRUD pour les livres du portfolio.
 * 
 * Fonctionnement:
 * - Les livres peuvent etre ajoutes via ISBN (donnees auto depuis Open Library)
 * - Fallback: saisie manuelle si ISBN non trouve ou non fourni
 * - Cache automatique des donnees Open Library (30 jours)
 * 
 * Endpoints publics: GET (liste, detail, stats, featured)
 * Endpoints proteges: POST, PUT, DELETE (authentification requise)
 *
 * @package App\Http\Controllers
 */
class BookController extends Controller
{
    /**
     * Service Open Library injecte.
     *
     * @var OpenLibraryService
     */
    private OpenLibraryService $openLibrary;

    /**
     * Constructeur avec injection de dependance.
     *
     * @param OpenLibraryService $openLibrary
     */
    public function __construct(OpenLibraryService $openLibrary)
    {
        $this->openLibrary = $openLibrary;
    }

    /**
     * Liste tous les livres avec filtres optionnels.
     *
     * @param Request $request
     * @return JsonResponse Liste des livres
     * 
     * @queryParam status string Filtrer par statut (read, reading, to-read)
     * @queryParam featured boolean Filtrer les livres mis en avant
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [{"id": 1, "title": "Clean Code", ...}]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = Book::query()->ordered();

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $books = $query->get();

        // Rafraichissement automatique du cache si necessaire
        $books->each(fn($book) => $this->ensureCache($book));

        return response()->json([
            'success' => true,
            'data' => $books,
        ]);
    }

    /**
     * Affiche un livre specifique.
     *
     * @param Book $book Livre (route model binding)
     * @return JsonResponse Detail du livre
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {"id": 1, "title": "Clean Code", ...}
     * }
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
     *
     * @return JsonResponse Livres featured
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
     *
     * @return JsonResponse Statistiques
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {"total": 10, "read": 5, "reading": 2, "to_read": 3}
     * }
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
     * Deux modes possibles:
     * 1. Avec ISBN: recuperation auto des donnees via Open Library
     * 2. Sans ISBN (fallback): saisie manuelle obligatoire (title requis)
     *
     * @param Request $request
     * @return JsonResponse Livre cree
     * 
     * @bodyParam isbn string ISBN-10 ou ISBN-13 (optionnel si title fourni)
     * @bodyParam title string Titre du livre (requis si pas d'ISBN ou ISBN non trouve)
     * @bodyParam author string Auteur (fallback)
     * @bodyParam cover_url string URL de la couverture (fallback)
     * @bodyParam status string Statut: read, reading, to-read
     * @bodyParam rating integer Note 1-5
     * @bodyParam review string Avis personnel
     * @bodyParam is_featured boolean Mettre en avant
     * 
     * @response 201 {
     *   "success": true,
     *   "data": {"id": 1, "title": "Clean Code", ...}
     * }
     * @response 422 {
     *   "success": false,
     *   "message": "ISBN not found. Please provide title manually.",
     *   "require_manual": true
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'isbn' => 'nullable|string|max:13',
            'title' => 'nullable|string|max:255',
            'author' => 'nullable|string|max:255',
            'cover_url' => 'nullable|url|max:500',
            'status' => 'in:read,reading,to-read',
            'rating' => 'nullable|integer|min:1|max:5',
            'review' => 'nullable|string|max:5000',
            'is_featured' => 'boolean',
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
                // ISBN trouve - utilisation des donnees Open Library
                $title = $cachedData['title'];
                $author = $cachedData['author'];
                $coverUrl = $cachedData['cover_url'];
            } elseif (!$title) {
                // ISBN non trouve et pas de titre fourni - demande de fallback
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
     *
     * @param Request $request
     * @param Book $book
     * @return JsonResponse Livre mis a jour
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
     *
     * @param Book $book
     * @return JsonResponse Confirmation
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
     * Utile si les donnees ont change ou si le cache est corrompu.
     *
     * @param Book $book
     * @return JsonResponse Livre avec cache rafraichi
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
     * Rafraichit automatiquement si expire (> 30 jours).
     *
     * @param Book $book
     * @return void
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
