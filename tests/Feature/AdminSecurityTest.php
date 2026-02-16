<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\LoginAttempt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Un invité ne peut pas voir les stats de sécurité.
     */
    public function test_guest_cannot_access_security_metrics()
    {
        $this->getJson('/api/v1/admin/security-stats')
             ->assertStatus(401);
    }

    /**
     * Un utilisateur non-admin est rejeté.
     */
    public function test_non_admin_cannot_access_security_metrics()
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'is_admin' => false // Assure-toi d'avoir cette colonne !
        ]);

        $this->actingAs($user)
             ->getJson('/api/v1/admin/security-stats')
             ->assertStatus(403);
    }

    /**
     * L'admin voit les stats et la structure est correcte.
     */
public function test_admin_can_access_security_metrics()
    {
        // 1. On s'assure de partir d'une table propre pour ce test précis
        \App\Models\LoginAttempt::query()->delete();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@portfolio.fr',
            'password' => bcrypt('password'),
            'is_admin' => true
        ]);

        // 2. On crée exactement UN record
        LoginAttempt::record('target@test.com', '1.2.3.4', false);

        $response = $this->actingAs($admin)
                         ->getJson('/api/v1/admin/security-stats');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'metrics' => ['total_attempts', 'failed_attempts', 'unique_ips_blocked'],
                     'alerts'
                 ]);

        // 3. Là, on est certain d'en avoir au moins 1 (celui du haut)
        // On peut utiliser assertGreaterThanOrEqual pour être plus flexible
        $this->assertGreaterThanOrEqual(1, $response->json('metrics.total_attempts'));
    }
}
