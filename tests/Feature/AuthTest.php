<?php

namespace Tests\Feature;

use App\Models\User;
// use App\Models\LoginAttempt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

public function test_login_with_valid_credentials()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'login_test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login_test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        // On va chercher la clé 'token' à l'intérieur de 'data'
        $this->assertArrayHasKey('token', $response->json('data'));
    }

    public function test_login_blocked_after_3_attempts()
    {
        $email = 'brute@test.com';
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong']);
        }

        $response = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong']);
        $response->assertStatus(429); // Code renvoyé par ton AuthController
    }

    public function test_me_returns_user_data()
    {
        $user = User::create(['name' => 'Me', 'email' => 'me@test.com', 'password' => 'p']);
        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertStatus(200)->assertJsonPath('data.email', 'me@test.com');
    }

    public function test_login_with_invalid_credentials()
    {
        User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'mauvais-password',
        ])->assertStatus(422); // ValidationException renvoie 422
    }

    public function test_logout_revokes_token()
    {
        $user = User::create(['name' => 'A', 'email' => 'logout@test.com', 'password' => bcrypt('p')]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/v1/auth/logout')
             ->assertStatus(200);

        // On vérifie que le token n'existe plus en base
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_login_lockout_expires_after_15_minutes()
    {
        $email = 'expiring@test.com';
        $ip = '1.1.1.1';

        // On simule 3 échecs
        for ($i = 0; $i < 3; $i++) {
            \App\Models\LoginAttempt::record($email, $ip, false);
        }

        $this->assertTrue(\App\Models\LoginAttempt::isBlocked($email, $ip));

        // On "voyage" dans le temps de 16 minutes
        $this->travel(16)->minutes();

        $this->assertFalse(\App\Models\LoginAttempt::isBlocked($email, $ip));
    }
}
