<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use DatabaseTransactions;

    public function test_store_creates_message()
    {
        $this->postJson('/api/v1/contact', [
            'name' => 'John',
            'email' => 'john@test.com',
            'message' => 'Hello'
        ])->assertStatus(201);

        $this->assertDatabaseHas('contact_messages', ['email' => 'john@test.com']);
    }
}
