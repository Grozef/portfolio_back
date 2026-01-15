<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Services\OpenLibraryService;
use Illuminate\Database\Seeder;

/**
 * Seeder BookSeeder - Creation de livres exemples.
 * 
 * Ajoute des livres de programmation classiques pour demonstrer
 * la fonctionnalite. Les donnees sont recuperees via Open Library.
 * 
 * Usage:
 *   php artisan db:seed --class=BookSeeder
 *
 * @package Database\Seeders
 */
class BookSeeder extends Seeder
{
    /**
     * Execute le seeder.
     * 
     * Pour chaque livre:
     * 1. Tente de recuperer les donnees via ISBN (Open Library)
     * 2. Utilise les donnees de fallback si l'API echoue
     * 3. Ajoute un delai entre les requetes API (respect rate limit)
     *
     * @return void
     */
    public function run(): void
    {
        $openLibrary = new OpenLibraryService();

        // Liste des livres avec ISBN et donnees personnelles
        $books = [
            [
                'isbn' => '9780132350884',
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'status' => 'read',
                'rating' => 5,
                'review' => 'Essential reading for any developer who wants to write maintainable code.',
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'isbn' => '9780135957059',
                'title' => 'The Pragmatic Programmer',
                'author' => 'David Thomas, Andrew Hunt',
                'status' => 'read',
                'rating' => 5,
                'review' => 'Timeless advice that transformed how I approach software development.',
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'isbn' => '9780201633610',
                'title' => 'Design Patterns',
                'author' => 'Gang of Four',
                'status' => 'read',
                'rating' => 4,
                'review' => 'The classic reference for design patterns. Dense but invaluable.',
                'is_featured' => true,
                'sort_order' => 3,
            ],
            [
                'isbn' => '9781449373320',
                'title' => 'Designing Data-Intensive Applications',
                'author' => 'Martin Kleppmann',
                'status' => 'reading',
                'rating' => null,
                'review' => null,
                'is_featured' => true,
                'sort_order' => 4,
            ],
            [
                'isbn' => '9780596517748',
                'title' => 'JavaScript: The Good Parts',
                'author' => 'Douglas Crockford',
                'status' => 'read',
                'rating' => 4,
                'review' => 'Short but impactful. Changed my understanding of JavaScript.',
                'is_featured' => false,
                'sort_order' => 5,
            ],
            [
                'isbn' => '9780134757599',
                'title' => 'Refactoring',
                'author' => 'Martin Fowler',
                'status' => 'to-read',
                'rating' => null,
                'review' => null,
                'is_featured' => false,
                'sort_order' => 6,
            ],
        ];

        foreach ($books as $bookData) {
            // Extraction des donnees
            $isbn = $bookData['isbn'];
            $fallbackTitle = $bookData['title'];
            $fallbackAuthor = $bookData['author'];
            
            unset($bookData['isbn'], $bookData['title'], $bookData['author']);

            // Tentative de recuperation via Open Library
            $this->command->info("Fetching: {$fallbackTitle}...");
            $cachedData = $openLibrary->getBookByIsbn($isbn);

            // Creation du livre
            Book::create([
                'isbn' => $isbn,
                'title' => $cachedData['title'] ?? $fallbackTitle,
                'author' => $cachedData['author'] ?? $fallbackAuthor,
                'cover_url' => $cachedData['cover_url'] ?? null,
                'cached_data' => $cachedData,
                'cached_at' => $cachedData ? now() : null,
                ...$bookData,
            ]);

            // Delai pour respecter les limites de l'API
            usleep(500000); // 0.5 seconde
        }

        $this->command->info('Books seeded successfully!');
    }
}
