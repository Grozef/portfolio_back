<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Book;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BookTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * test_index_returns_paginated_books()
     */
    public function test_index_returns_paginated_books()
    {
        // Création manuelle de plusieurs livres pour tester la pagination
        for ($i = 1; $i <= 15; $i++) {
            Book::create([
                'title' => "Livre $i",
                'status' => 'read'
            ]);
        }

        $response = $this->getJson('/api/v1/books');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'meta']);
    }

    /**
     * test_store_creates_book_with_isbn()
     */
    public function test_store_creates_book_with_isbn()
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.fr', 'password' => bcrypt('password')]);

        $payload = [
            'title'  => 'Le Seigneur des Anneaux',
            'isbn'   => '9782266154116',
            'status' => 'read' // Corrigé ici : on utilise 'read' au lieu de 'want_to_read'
        ];

        $this->actingAs($user)
             ->postJson('/api/v1/books', $payload)
             ->assertStatus(201);

        $this->assertDatabaseHas('books', ['isbn' => '9782266154116']);
    }

    /**
     * test_store_validates_isbn_format()
     */
    public function test_store_validates_isbn_format()
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin2@test.fr', 'password' => bcrypt('password')]);

        $this->actingAs($user)
             ->postJson('/api/v1/books', ['isbn' => 'format-invalide'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['isbn']);
    }

    /**
     * test_store_prevents_duplicate_isbn()
     */
    public function test_store_prevents_duplicate_isbn()
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin3@test.fr', 'password' => bcrypt('password')]);
        Book::create(['title' => 'Livre Existant', 'isbn' => '1111', 'status' => 'read']);

        $this->actingAs($user)
             ->postJson('/api/v1/books', [
                 'title' => 'Doublon',
                 'isbn'  => '1111',
                 'status' => 'read'
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['isbn']);
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
        $uniquePrefix = "FEATURED_".uniqid();

        for($i=0; $i<8; $i++) {
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
}
