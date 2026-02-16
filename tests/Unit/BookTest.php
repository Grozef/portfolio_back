<?php

namespace Tests\Unit;

use App\Models\Book;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BookTest extends TestCase
{
    use DatabaseTransactions;

public function test_ordered_scope_sorts_by_sort_order_then_date()
{
    $b1 = Book::create(['title' => 'A', 'status' => 'read', 'sort_order' => 1]);
    $b2 = Book::create(['title' => 'B', 'status' => 'read', 'sort_order' => 2]);

    $results = Book::ordered()->get();

    // On utilise search() qui renvoie l'index (0, 1, 2...)
    $index1 = $results->pluck('id')->search($b1->id);
    $index2 = $results->pluck('id')->search($b2->id);

    $this->assertTrue($index1 < $index2);
}

    public function test_featured_scope_filters_featured_books()
    {
        Book::create(['title' => 'Featured', 'status' => 'read', 'is_featured' => true]);

        $this->assertGreaterThanOrEqual(1, Book::featured()->count());
    }

    public function test_by_status_scope_filters_correctly()
    {
        Book::create(['title' => 'Status Test', 'status' => 'reading']);

        $this->assertGreaterThanOrEqual(1, Book::byStatus('reading')->count());
    }

    public function test_needs_cache_refresh_logic()
    {
        // Cas 1 : Pas d'ISBN -> false
        $book = new Book(['isbn' => null]);
        $this->assertFalse($book->needsCacheRefresh());

        // Cas 2 : ISBN présent mais jamais caché -> true
        $book->isbn = '1234567890';
        $book->cached_at = null;
        $this->assertTrue($book->needsCacheRefresh());

        // Cas 3 : Caché il y a 31 jours -> true
        $book->cached_at = now()->subDays(31);
        $this->assertTrue($book->needsCacheRefresh());

        // Cas 4 : Caché il y a 10 jours -> false
        $book->cached_at = now()->subDays(10);
        $this->assertFalse($book->needsCacheRefresh());
    }

    public function test_update_cache_sets_dates_correctly()
    {
        $book = Book::create(['title' => 'Cache Test', 'status' => 'read']);
        $data = ['title' => 'Titre API', 'author' => 'Auteur API'];

        $book->updateCache($data);

        $this->assertEquals($data, $book->fresh()->cached_data);
        $this->assertNotNull($book->fresh()->cached_at);
    }

    public function test_book_accessors_prioritize_cached_data()
    {
        $book = new Book([
            'title' => 'Titre Manuel',
            'author' => 'Auteur Manuel',
            'cached_data' => [
                'title' => 'Titre API',
                'author' => 'Auteur API',
                'cover_url' => 'http://api-cover.com'
            ]
        ]);

        // Priorité au cache
        $this->assertEquals('Titre API', $book->display_title);
        $this->assertEquals('Auteur API', $book->display_author);
        $this->assertEquals('http://api-cover.com', $book->display_cover_url);

        // Si le cache est vide, on prend le manuel
        $book->cached_data = null;
        $this->assertEquals('Titre Manuel', $book->display_title);
        $this->assertEquals('Auteur Manuel', $book->display_author);
    }
}
