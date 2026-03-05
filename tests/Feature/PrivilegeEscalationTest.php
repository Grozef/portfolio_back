<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Book;
use App\Models\CarouselImage;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tests de privilege escalation (C2) :
 * Un utilisateur authentifié mais non-admin doit recevoir 403 sur toutes les routes admin.
 */
class PrivilegeEscalationTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_admin' => false]);
    }

    // --- Messages ---

    public function test_non_admin_cannot_list_messages()
    {
        $this->actingAs($this->user)->getJson('/api/v1/messages')->assertStatus(403);
    }

    public function test_non_admin_cannot_show_message()
    {
        $msg = ContactMessage::create(['name' => 'T', 'email' => 't@t.fr', 'message' => 'test message content']);
        $this->actingAs($this->user)->getJson("/api/v1/messages/{$msg->id}")->assertStatus(403);
    }

    public function test_non_admin_cannot_delete_message()
    {
        $msg = ContactMessage::create(['name' => 'T', 'email' => 't@t.fr', 'message' => 'test message content']);
        $this->actingAs($this->user)->deleteJson("/api/v1/messages/{$msg->id}")->assertStatus(403);
    }

    // --- Books ---

    public function test_non_admin_cannot_create_book()
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/books', ['title' => 'Test', 'status' => 'read'])
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_update_book()
    {
        $book = Book::create(['title' => 'Test', 'status' => 'read']);
        $this->actingAs($this->user)
            ->putJson("/api/v1/books/{$book->id}", ['title' => 'Updated'])
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_delete_book()
    {
        $book = Book::create(['title' => 'Test', 'status' => 'read']);
        $this->actingAs($this->user)->deleteJson("/api/v1/books/{$book->id}")->assertStatus(403);
    }

    // --- Carousel ---

    public function test_non_admin_cannot_store_carousel_image()
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/carousel', ['image_url' => '/storage/carousel/test.jpg'])
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_update_carousel_image()
    {
        $img = CarouselImage::create(['title' => 'T', 'image_url' => '/storage/carousel/test.jpg']);
        $this->actingAs($this->user)
            ->putJson("/api/v1/carousel/{$img->id}", ['title' => 'Updated'])
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_delete_carousel_image()
    {
        $img = CarouselImage::create(['title' => 'T', 'image_url' => '/storage/carousel/test.jpg']);
        $this->actingAs($this->user)->deleteJson("/api/v1/carousel/{$img->id}")->assertStatus(403);
    }

    public function test_non_admin_cannot_upload_carousel_image()
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/carousel/upload', [])
            ->assertStatus(403);
    }

    // --- Cookies cleanup ---

    public function test_non_admin_cannot_cleanup_cookies()
    {
        $this->actingAs($this->user)->deleteJson('/api/v1/cookies/cleanup')->assertStatus(403);
    }

    public function test_guest_cannot_cleanup_cookies()
    {
        $this->deleteJson('/api/v1/cookies/cleanup')->assertStatus(401);
    }
}
