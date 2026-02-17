<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\CookiePreference;
use App\Models\CookieEasterEgg;
use App\Http\Resources\BookResource;
use App\Http\Resources\CookiePreferenceResource;
use App\Http\Resources\CookieEasterEggResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResourceTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
public function book_resource_formats_correctly(): void
    {
        $book = Book::create([
            'title' => 'Titre Manuel',
            'author' => 'Auteur Manuel',
            'isbn' => '1234567890',
            'status' => 'read',
            'is_featured' => true,
            'cached_data' => [
                'title' => 'Titre API',
                'author' => 'Auteur API',
                'cover_url' => 'https://api-cover.com/123',
                'description' => 'Ma description API',
                'source' => 'openlibrary'
            ],
            'cached_at' => now()
        ]);

        $resource = (new BookResource($book))->resolve();

        // Testing the Accessors (The Resource uses $this->display_title)
        $this->assertEquals('Titre API', $resource['display_title']);
        $this->assertEquals('Auteur API', $resource['display_author']);

        // Testing the Raw fields
        $this->assertEquals('Titre Manuel', $resource['title']);
        $this->assertEquals('Auteur Manuel', $resource['author']);

        // Testing Source Logic
        $this->assertEquals('openlibrary', $resource['source']);

        // Check ISO 8601 format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $resource['created_at']);
    }

    /** @test */
    public function cookie_preference_resource_formats_correctly()
    {
        $preference = CookiePreference::create([
            'session_id' => 'sess_test_999',
            'analytics_consent' => true,
            'marketing_consent' => false,
            'preferences_consent' => true,
            'consent_date' => now(),
            'expires_at' => now()->addYear()
        ]);

        $resource = (new CookiePreferenceResource($preference))->resolve();

        $this->assertTrue($resource['analytics_consent']);
        $this->assertFalse($resource['marketing_consent']);
        $this->assertTrue($resource['has_consent']);
        $this->assertNotNull($resource['expires_at']);
    }

    /** @test */
    public function cookie_easter_egg_resource_formats_correctly()
    {
        $egg = CookieEasterEgg::create([
            'session_id' => 'sess_test_999',
            'egg_id' => 'secret_level',
            'discovered_at' => now(),
            'expires_at' => now()->addYear(),
            'metadata' => ['browser' => 'test']
        ]);

        $resource = (new CookieEasterEggResource($egg))->resolve();

        $this->assertEquals('secret_level', $resource['egg_id']);
        $this->assertEquals('test', $resource['metadata']['browser']);
    }
}
