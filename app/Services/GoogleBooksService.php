<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Google Books API Service
 *
 * Provides book information from Google Books API with better coverage
 * for French and international titles compared to OpenLibrary.
 *
 * API Documentation: https://developers.google.com/books/docs/v1/using
 * Free tier: 1000 requests/day with API key, 100/day/IP without key
 *
 * @package App\Services
 */
class GoogleBooksService
{
    /**
     * Google Books API base URL
     *
     * @var string
     */
    private string $baseUrl = 'https://www.googleapis.com/books/v1';

    /**
     * Cache duration in days
     *
     * @var int
     */
    private int $cacheDays = 30;

    /**
     * HTTP request timeout in seconds
     *
     * @var int
     */
    private int $timeout = 10;

    /**
     * Google Books API key (optional)
     *
     * @var string|null
     */
    private ?string $apiKey;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->apiKey = config('services.google_books.api_key');
    }

    /**
     * Get book information by ISBN
     *
     * Searches Google Books API for a book matching the provided ISBN.
     * Results are cached for 30 days to minimize API calls.
     *
     * @param string $isbn ISBN-10 or ISBN-13
     * @return array|null Book data or null if not found
     */
    public function getBookByIsbn(string $isbn): ?array
    {
        $isbn = $this->cleanIsbn($isbn);
        $cacheKey = "googlebooks_isbn_{$isbn}";

        return Cache::remember($cacheKey, now()->addDays($this->cacheDays), function () use ($isbn) {
            return $this->fetchBookFromApi($isbn);
        });
    }

    /**
     * Force cache refresh for an ISBN
     *
     * @param string $isbn ISBN to refresh
     * @return array|null New data or null if not found
     */
    public function refreshCache(string $isbn): ?array
    {
        $isbn = $this->cleanIsbn($isbn);
        $cacheKey = "googlebooks_isbn_{$isbn}";

        Cache::forget($cacheKey);
        return $this->getBookByIsbn($isbn);
    }

    /**
     * Fetch book data from Google Books API
     *
     * @param string $isbn Cleaned ISBN
     * @return array|null Formatted book data or null
     */
    private function fetchBookFromApi(string $isbn): ?array
    {
        try {
            // Build query parameters
            $params = [
                'q' => "isbn:{$isbn}",
                'maxResults' => 1,
            ];

            // Add API key if available
            if ($this->apiKey) {
                $params['key'] = $this->apiKey;
            }

            // Make API request
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/volumes", $params);

            if (!$response->successful()) {
                Log::warning("Google Books API error for ISBN {$isbn}", [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();

            // Check if results were found
            if (!isset($data['items']) || empty($data['items'])) {
                Log::info("ISBN {$isbn} not found in Google Books");
                return null;
            }

            return $this->formatBookData($data['items'][0], $isbn);

        } catch (\Exception $e) {
            Log::error("Google Books API exception for ISBN {$isbn}", [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Clean ISBN by removing all non-alphanumeric characters
     *
     * @param string $isbn Raw ISBN
     * @return string Cleaned ISBN (digits and X only)
     */
    private function cleanIsbn(string $isbn): string
    {
        return preg_replace('/[^0-9X]/i', '', $isbn);
    }

    /**
     * Format raw API data into standardized book structure
     *
     * @param array $item Raw book item from API
     * @param string $isbn ISBN for fallback cover URL
     * @return array Formatted book data
     */
    private function formatBookData(array $item, string $isbn): array
    {
        $volumeInfo = $item['volumeInfo'] ?? [];

        // Extract authors
        $authors = $volumeInfo['authors'] ?? [];
        $authorString = implode(', ', $authors);

        // Get best quality cover image
        $coverUrl = $this->extractCoverUrl($volumeInfo, $isbn);

        // Get description
        $description = $volumeInfo['description'] ?? null;

        // Publishers
        $publishers = isset($volumeInfo['publisher'])
            ? [$volumeInfo['publisher']]
            : [];

        // Categories/subjects (limit to 5)
        $subjects = array_slice($volumeInfo['categories'] ?? [], 0, 5);

        // Build standardized response
        return [
            'title' => $volumeInfo['title'] ?? null,
            'authors' => $authors,
            'author' => $authorString,
            'cover_url' => $coverUrl,
            'description' => $description,
            'pages' => $volumeInfo['pageCount'] ?? null,
            'publish_date' => $volumeInfo['publishedDate'] ?? null,
            'publishers' => $publishers,
            'subjects' => $subjects,
            'source' => 'google_books',
        ];
    }

    /**
     * Extract best quality cover image URL
     *
     * @param array $volumeInfo Volume info from API
     * @param string $isbn ISBN for fallback
     * @return string Cover image URL
     */
    private function extractCoverUrl(array $volumeInfo, string $isbn): string
    {
        $imageLinks = $volumeInfo['imageLinks'] ?? [];

        // Priority: extraLarge > large > medium > thumbnail
        // Also ensure HTTPS protocol
        if (isset($imageLinks['extraLarge'])) {
            return str_replace('http://', 'https://', $imageLinks['extraLarge']);
        }

        if (isset($imageLinks['large'])) {
            return str_replace('http://', 'https://', $imageLinks['large']);
        }

        if (isset($imageLinks['medium'])) {
            return str_replace('http://', 'https://', $imageLinks['medium']);
        }

        if (isset($imageLinks['thumbnail'])) {
            return str_replace('http://', 'https://', $imageLinks['thumbnail']);
        }

        // Fallback to OpenLibrary covers API
        return "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg";
    }
}