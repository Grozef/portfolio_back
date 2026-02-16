<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContactMessageTest extends TestCase {
    use DatabaseTransactions;

    public function test_public_store_and_admin_management() {
        $this->postJson('/api/v1/contact', ['name' => 'J', 'email' => 'j@t.com', 'message' => 'Hello'])->assertStatus(201);

        $user = User::create(['name' => 'A', 'email' => 'm@t.com', 'password' => 'p']);
        $msg = ContactMessage::create(['name' => 'X', 'email' => 'x@x.com', 'message' => 'M']);

        $this->actingAs($user)->patchJson("/api/v1/messages/{$msg->id}/read")->assertStatus(200);
        $this->actingAs($user)->deleteJson("/api/v1/messages/{$msg->id}")->assertStatus(200);
    }
}
