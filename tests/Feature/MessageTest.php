<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use DatabaseTransactions;

    // --- LA CORRECTION EST ICI ---
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // On crée l'admin une seule fois pour tous les tests de cette classe
        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'is_admin' => true
        ]);
    }

    /**
     * Test de sécurité : Accès interdit sans auth
     */
    public function test_index_requires_auth()
    {
        $this->getJson('/api/v1/messages')->assertStatus(401);
    }

    /**
     * Test de la pagination (Crucial pour ton score de coverage !)
     */
    public function test_admin_can_list_messages_with_pagination()
    {
        // Création manuelle de 15 messages
        for ($i = 1; $i <= 15; $i++) {
            ContactMessage::create([
                'name' => "User $i",
                'email' => "user$i@test.com",
                'message' => "Message content $i"
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/messages?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /**
     * Test du marquage lu / non-lu
     */
    public function test_admin_can_mark_message_as_read_and_unread()
    {
        $message = ContactMessage::create([
            'name' => 'Sender',
            'email' => 's@t.com',
            'message' => 'Ceci est un message de test'
        ]);

        // Passage à "lu"
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/messages/{$message->id}/read")
            ->assertStatus(200);

        $this->assertNotNull($message->fresh()->read_at);

        // Passage à "non-lu"
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/messages/{$message->id}/unread")
            ->assertStatus(200);

        $this->assertNull($message->fresh()->read_at);
    }

    /**
     * Test de la suppression
     */
    public function test_admin_can_delete_message()
    {
        $message = ContactMessage::create([
            'name' => 'Spammer',
            'email' => 'spam@bot.com',
            'message' => 'Buy cheap watches!'
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/messages/{$message->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }

    public function test_show_requires_auth()
    {
        $msg = ContactMessage::create(['name' => 'T', 'email' => 't@t.fr', 'message' => 'test message content']);
        $this->getJson("/api/v1/messages/{$msg->id}")->assertStatus(401);
    }
}
