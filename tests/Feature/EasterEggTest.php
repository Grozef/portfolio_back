<?php

namespace Tests\Feature;

use Tests\TestCase;

class EasterEggTest extends TestCase
{
    /**
     * Teste tous les endpoints du EasterEggController
     */
public function test_easter_egg_endpoints_return_cookie_messages()
    {
        // 1. Progress (GET) - OK
        $this->getJson('/api/v1/easter-eggs/progress')
            ->assertStatus(200);

        // 2. Discover (POST) - On ajoute le egg_id pour satisfaire la validation
        $this->postJson('/api/v1/easter-eggs/discover', [
            'egg_id' => 'exif_secret_001'
        ])
        ->assertStatus(200)
        ->assertJson(['success' => true]);

        // 3. Reset (DELETE) - OK
        $this->deleteJson('/api/v1/easter-eggs/reset')
            ->assertStatus(200);

        // 4. Statistics (GET) - OK
        $this->getJson('/api/v1/easter-eggs/statistics')
            ->assertStatus(200);
    }
}
