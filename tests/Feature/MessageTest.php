<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_requires_auth()
    {
        $this->getJson('/api/v1/messages')->assertStatus(401);
    }

public function test_delete_removes_message()
{
    /** @var User $user */
    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin_msg@test.com',
        'password' => bcrypt('password')
    ]);

    $message = \App\Models\ContactMessage::create([
        'name' => 'Sender',
        'email' => 'sender@test.com',
        'message' => 'Test message content'
    ]);

    $this->actingAs($user)
         ->deleteJson("/api/v1/messages/{$message->id}")
         ->assertStatus(200);

    $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
}

public function test_mark_as_read_updates_timestamp()
    {
        $user = User::create(['name' => 'A', 'email' => 'a@a.fr', 'password' => 'p']);
        $msg = ContactMessage::create(['name' => 'T', 'email' => 't@t.fr', 'message' => 'test']);

        $this->actingAs($user)->patchJson("/api/v1/messages/{$msg->id}/read")->assertStatus(200);
        $this->assertNotNull($msg->fresh()->read_at);
    }
}
