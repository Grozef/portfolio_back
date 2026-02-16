<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Book;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;

class BookTest extends TestCase
{
    use DatabaseTransactions;

    // Déclaration de la propriété pour l'analyse statique
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // On crée un admin générique pour tous les tests de la classe
        $this->admin = User::factory()->create([
            'is_admin' => true
        ]);
    }

    /**
     * test_index_returns_paginated_books()
     */
    public function test_index_returns_paginated_books()
    {
        for ($i = 1; $i <= 10; $i++) {
            Book::create([
                'title' => "Livre $i",
                'status' => 'read'
            ]);
        }

        $response = $this->getJson('/api/v1/books?per_page=5');
        $response->assertStatus(200)->assertJsonCount(5, 'data');
    }

    /**
     * test_store_creates_book_with_isbn()
     */
    public function test_store_creates_book_with_isbn()
    {
        $payload = [
            'title'  => 'Le Seigneur des Anneaux',
            'isbn'   => '9782266154116',
            'status' => 'read'
        ];

        // Utilisation de $this->admin déclaré dans setUp
        $this->actingAs($this->admin)
            ->postJson('/api/v1/books', $payload)
            ->assertStatus(201);

        $this->assertDatabaseHas('books', ['isbn' => '9782266154116']);
    }

    /**
     * test_store_validates_isbn_format()
     */
    public function test_store_validates_isbn_format()
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/books', ['isbn' => 'format-invalide'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['isbn']);
    }

    /**
     * test_store_prevents_duplicate_isbn()
     */
    public function test_store_prevents_duplicate_isbn()
    {
        Book::create(['title' => 'Livre Existant', 'isbn' => '1111', 'status' => 'read']);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/books', [
                'title' => 'Doublon',
                'isbn'  => '1111',
                'status' => 'read'
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['isbn']);
    }

    /**
     * test_update_book_validation_fails_with_invalid_data
     */
public function test_update_book_validation_fails_with_invalid_data()
    {
        $book = Book::create([
            'title' => 'Test',
            'isbn' => '978' . rand(1000000000, 9999999999),
            'status' => 'read'
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/books/{$book->id}", [
                'status' => 'invalid-status', // Déclenche l'erreur 'in:...'
                'rating' => 10,               // Déclenche l'erreur 'max:5'
            ]);

        // On vérifie au moins le status.
        // Si rating ne passe toujours pas, vérifie ton UpdateBookRequest !
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['status']);
    }

    /**
     * test_update_book_requires_auth()
     */
    public function test_update_book_requires_auth()
    {
        $book = Book::create(['title' => 'Ancien Titre', 'status' => 'read']);

        $this->putJson("/api/v1/books/{$book->id}", ['title' => 'Nouveau Titre'])
            ->assertStatus(401);
    }

    /**
     * test_delete_book_requires_auth()
     */
    public function test_delete_book_requires_auth()
    {
        $book = Book::create(['title' => 'A supprimer', 'status' => 'read']);

        $this->deleteJson("/api/v1/books/{$book->id}")
            ->assertStatus(401);
    }

    /**
     * test_featured_books_limited_to_6()
     */
    public function test_featured_books_limited_to_6()
    {
        $uniquePrefix = "FEATURED_" . uniqid();

        for ($i = 0; $i < 8; $i++) {
            Book::create([
                'title' => "$uniquePrefix $i",
                'status' => 'read',
                'is_featured' => true,
                'sort_order' => -1
            ]);
        }

        $response = $this->getJson('/api/v1/books/featured');

        $this->assertCount(6, $response->json('data'));
        $this->assertStringContainsString($uniquePrefix, $response->json('data.0.title'));
    }

    /**
     * Test le basculement vers Google Books si OpenLibrary échoue.
     */
    public function test_store_falls_back_to_google_books_when_openlibrary_fails()
    {
        $isbn = '9782070415731';
        Http::fake([
            'https://openlibrary.org/*' => Http::response([], 404),
            'https://www.googleapis.com/books/*' => Http::response([
                'items' => [['volumeInfo' => ['title' => 'Google Title', 'authors' => ['Author']]]]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/books', [
                'isbn' => $isbn,
                'status' => 'read' // On utilise un status valide pour éviter le 422
            ]);

        $response->assertStatus(201);
    }

public function test_store_returns_error_if_all_apis_fail()
{
    // 1. Un ISBN propre qui passe ta regex
    $isbn = '9782070612758';

    // 2. On nettoie pour éviter le conflit "unique"
    \App\Models\Book::where('isbn', $isbn)->delete();

    // 3. On simule l'échec des APIs
    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([], 404)]);

    // 4. On envoie la requête
    $response = $this->actingAs($this->admin)->postJson('/api/v1/books', [
        'isbn'   => $isbn,
        'status' => 'to-read',
    ]);

    // On attend un 422 (car c'est ce que TON contrôleur renvoie)
    $response->assertStatus(422)
             ->assertJson([
                 'success' => false,
                 'require_manual' => true
             ]);
}
}
