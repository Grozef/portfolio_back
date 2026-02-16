<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Book;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BookTest extends TestCase
{
    use DatabaseTransactions;

public function test_index_returns_paginated_books()
    {
        Book::create(['title' => 'Test', 'status' => 'read']);
        $response = $this->getJson('/api/v1/books');
        $response->assertStatus(200)->assertJsonStructure(['data', 'meta']);
    }

    public function test_store_validates_isbn_format()
    {
        $user = User::create(['name' => 'A', 'email' => 'a@a.fr', 'password' => 'p']);
        $this->actingAs($user)->postJson('/api/v1/books', ['isbn' => 'invalid'])
             ->assertStatus(422)->assertJsonValidationErrors(['isbn']);
    }

public function test_featured_books_limited_to_6()
{
    // 1. On crée des livres avec un titre très spécifique
    $uniquePrefix = "UNIQUE_TEST_".uniqid();
    for($i=0; $i<8; $i++) {
        Book::create([
            'title' => "$uniquePrefix $i",
            'status' => 'read',
            'is_featured' => true,
            'sort_order' => -1 // On les force en premier pour être sûr qu'ils sortent
        ]);
    }

    $response = $this->getJson('/api/v1/books/featured');

    // 2. On vérifie le nombre total (la limite de 6)
    $this->assertCount(6, $response->json('data'));

    // 3. On vérifie que ce sont bien nos livres de test qui sont là
    $this->assertStringContainsString($uniquePrefix, $response->json('data.0.title'));
}
}
