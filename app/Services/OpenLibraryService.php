<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service OpenLibraryService - Integration avec l'API Open Library.
 * 
 * Permet de recuperer les informations d'un livre via son ISBN
 * depuis l'API gratuite Open Library (openlibrary.org).
 * 
 * Les donnees sont mises en cache pendant 30 jours pour optimiser
 * les performances et respecter les limites de l'API.
 *
 * @package App\Services
 * @see https://openlibrary.org/developers/api
 */
class OpenLibraryService
{
    /**
     * URL de base de l'API Open Library.
     *
     * @var string
     */
    private string $baseUrl = 'https://openlibrary.org';

    /**
     * Duree du cache en jours.
     *
     * @var int
     */
    private int $cacheDays = 30;

    /**
     * Timeout des requetes HTTP en secondes.
     *
     * @var int
     */
    private int $timeout = 10;

    /**
     * Recupere les informations d'un livre via son ISBN.
     * 
     * Les donnees sont mises en cache pour eviter les appels API repetitifs.
     * Retourne null si le livre n'est pas trouve ou en cas d'erreur.
     *
     * @param string $isbn ISBN-10 ou ISBN-13 du livre
     * @return array|null Donnees du livre ou null si non trouve
     */
    public function getBookByIsbn(string $isbn): ?array
    {
        $isbn = $this->cleanIsbn($isbn);
        $cacheKey = "openlib_isbn_{$isbn}";

        return Cache::remember($cacheKey, now()->addDays($this->cacheDays), function () use ($isbn) {
            return $this->fetchBookFromApi($isbn);
        });
    }

    /**
     * Force le rafraichissement du cache pour un ISBN.
     *
     * @param string $isbn ISBN du livre
     * @return array|null Nouvelles donnees ou null si non trouve
     */
    public function refreshCache(string $isbn): ?array
    {
        $isbn = $this->cleanIsbn($isbn);
        $cacheKey = "openlib_isbn_{$isbn}";
        
        Cache::forget($cacheKey);

        return $this->getBookByIsbn($isbn);
    }

    /**
     * Effectue l'appel API pour recuperer les donnees du livre.
     *
     * @param string $isbn ISBN nettoye
     * @return array|null Donnees formatees ou null
     */
    private function fetchBookFromApi(string $isbn): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/api/books.json", [
                    'bibkeys' => "ISBN:{$isbn}",
                    'format' => 'json',
                    'jscmd' => 'data',
                ]);

            if (!$response->successful()) {
                Log::warning("OpenLibrary API error for ISBN {$isbn}", [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json("ISBN:{$isbn}");

            if (!$data) {
                Log::info("ISBN {$isbn} not found in OpenLibrary");
                return null;
            }

            return $this->formatBookData($data, $isbn);

        } catch (\Exception $e) {
            Log::error("OpenLibrary API exception for ISBN {$isbn}", [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Nettoie l'ISBN en supprimant tirets et espaces.
     *
     * @param string $isbn ISBN brut
     * @return string ISBN nettoye (chiffres et X uniquement)
     */
    private function cleanIsbn(string $isbn): string
    {
        return preg_replace('/[^0-9X]/i', '', $isbn);
    }

    /**
     * Formate les donnees brutes de l'API en structure utilisable.
     *
     * @param array $data Donnees brutes de l'API
     * @param string $isbn ISBN du livre
     * @return array Donnees formatees
     */
    private function formatBookData(array $data, string $isbn): array
    {
        // Extraction des auteurs
        $authors = [];
        if (isset($data['authors'])) {
            foreach ($data['authors'] as $author) {
                if (isset($author['name'])) {
                    $authors[] = $author['name'];
                }
            }
        }

        // URL de la couverture (priorite: large > medium > fallback)
        $coverUrl = $this->extractCoverUrl($data, $isbn);

        return [
            'title' => $data['title'] ?? null,
            'authors' => $authors,
            'author' => implode(', ', $authors),
            'cover_url' => $coverUrl,
            'description' => $this->extractDescription($data),
            'pages' => $data['number_of_pages'] ?? null,
            'publish_date' => $data['publish_date'] ?? null,
            'publishers' => $this->extractPublishers($data),
            'subjects' => $this->extractSubjects($data),
        ];
    }

    /**
     * Extrait l'URL de la couverture.
     *
     * @param array $data Donnees de l'API
     * @param string $isbn ISBN pour le fallback
     * @return string URL de la couverture
     */
    private function extractCoverUrl(array $data, string $isbn): string
    {
        if (isset($data['cover']['large'])) {
            return $data['cover']['large'];
        }
        
        if (isset($data['cover']['medium'])) {
            return $data['cover']['medium'];
        }

        // Fallback vers l'API covers
        return "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg";
    }

    /**
     * Extrait la description du livre.
     * La description peut etre une string ou un objet avec une cle 'value'.
     *
     * @param array $data Donnees de l'API
     * @return string|null Description ou null
     */
    private function extractDescription(array $data): ?string
    {
        if (!isset($data['description'])) {
            return null;
        }

        if (is_string($data['description'])) {
            return $data['description'];
        }

        if (is_array($data['description']) && isset($data['description']['value'])) {
            return $data['description']['value'];
        }

        return null;
    }

    /**
     * Extrait la liste des editeurs.
     *
     * @param array $data Donnees de l'API
     * @return array Liste des noms d'editeurs
     */
    private function extractPublishers(array $data): array
    {
        if (!isset($data['publishers'])) {
            return [];
        }

        return array_map(
            fn($p) => $p['name'] ?? '',
            $data['publishers']
        );
    }

    /**
     * Extrait les sujets/categories (max 5).
     *
     * @param array $data Donnees de l'API
     * @return array Liste des sujets
     */
    private function extractSubjects(array $data): array
    {
        if (!isset($data['subjects'])) {
            return [];
        }

        return array_slice(
            array_map(fn($s) => $s['name'] ?? '', $data['subjects']),
            0,
            5
        );
    }
}
