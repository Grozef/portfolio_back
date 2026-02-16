<?php

namespace Tests\Unit;

use App\Models\LoginAttempt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class LoginAttemptTest extends TestCase
{
    use DatabaseTransactions;

    public function test_is_blocked_returns_true_after_3_failures()
    {
        $email = 'unit@test.com';
        $ip = '1.2.3.4';

        for ($i = 0; $i < 3; $i++) {
            LoginAttempt::record($email, $ip, false);
        }

        $this->assertTrue(LoginAttempt::isBlocked($email, $ip));
    }

    public function test_cleanup_removes_attempts_older_than_24h()
    {
        DB::table('login_attempts')->insert([
            'email' => 'old@test.com',
            'ip_address' => '1.1.1.1',
            'successful' => false,
            'attempted_at' => now()->subHours(25)
        ]);

        $deletedCount = LoginAttempt::cleanup();

        $this->assertGreaterThanOrEqual(1, $deletedCount);
        $this->assertDatabaseMissing('login_attempts', ['email' => 'old@test.com']);
    }

public function test_remaining_lockout_seconds_calculation()
{
    $email = 'lockout@test.com';
    $ip = '1.2.3.4';

    // Ton modèle utilise LOCKOUT_MINUTES = 15.
    // On crée une tentative échouée il y a 10 minutes.
    DB::table('login_attempts')->insert([
        'email' => $email,
        'ip_address' => $ip,
        'successful' => false,
        'attempted_at' => now()->subMinutes(10)
    ]);

    // On retire le "get" pour correspondre au modèle
    $remaining = LoginAttempt::remainingLockoutSeconds($email, $ip);

    // Il devrait rester environ 5 minutes (300 secondes)
    $this->assertGreaterThan(0, $remaining);
    $this->assertLessThanOrEqual(900, $remaining); // 900s = 15 min max
}
}
