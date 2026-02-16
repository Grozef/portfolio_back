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
            'message' => 'Ce qu\'il y a d\'admirable dans le bonheur des autres, c\'est qu\'on y croit.'
        ])->assertStatus(201);

        $this->assertDatabaseHas('contact_messages', ['email' => 'john@test.com']);
    }

    public function test_store_validates_email_format()
    {
        $this->postJson('/api/v1/contact', [
            'name' => 'John',
            'email' => 'pas-un-email',
            'message' => 'Ce qu\'il y a d\'admirable dans le bonheur des autres, c\'est qu\'on y croit.'
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_store_validates_max_message_length()
    {
        $this->postJson('/api/v1/contact', [
            'name' => 'John',
            'email' => 'john@test.com',
            'message' => str_repeat('a', 5001) // Supposons une limite à 5000
        ])->assertStatus(422)->assertJsonValidationErrors(['message']);
    }

    public function test_store_rate_limited_5_per_minute()
    {
        // On simule 5 requêtes
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/contact', [
                'name' => 'John',
                'email' => "test$i@test.com",
                'message' => 'Ce qu\'il y a d\'admirable dans le bonheur des autres, c\'est qu\'on y croit.'
            ])->assertStatus(201);
        }

        // La 6ème doit échouer
        $this->postJson('/api/v1/contact', [
            'name' => 'John',
            'email' => 'test6@test.com',
            'message' => 'Ce qu\'il y a d\'admirable dans le bonheur des autres, c\'est qu\'on y croit.'
        ])->assertStatus(429); // Too Many Requests
    }

    public function test_store_fails_if_honeypot_is_filled()
{
    $this->postJson('/api/v1/contact', [
        'name' => 'Bot',
        'email' => 'bot@spam.com',
        'message' => 'I am a robot filling fields',
        'website' => 'http://malicious-site.com' // Le bot remplit ça
    ])->assertStatus(201); // On vérifie qu'on renvoie 201 (la feinte)

    // Mais on vérifie que la base de données est vide
    $this->assertDatabaseMissing('contact_messages', ['email' => 'bot@spam.com']);
}
}
