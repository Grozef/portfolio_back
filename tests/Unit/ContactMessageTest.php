<?php

namespace Tests\Unit;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContactMessageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_read_and_unread_scopes()
    {
        // Création d'un message non lu
        ContactMessage::create([
            'name' => 'Unread',
            'email' => 'u@t.com',
            'message' => '...',
            'read_at' => null
        ]);

        // Création d'un message lu
        ContactMessage::create([
            'name' => 'Read',
            'email' => 'r@t.com',
            'message' => '...',
            'read_at' => now()
        ]);

        $this->assertGreaterThanOrEqual(1, ContactMessage::unread()->count());
        $this->assertGreaterThanOrEqual(1, ContactMessage::read()->count());

        // Vérification qu'un message lu n'est pas dans le scope unread
        $this->assertFalse(ContactMessage::unread()->where('name', 'Read')->exists());
    }
}
