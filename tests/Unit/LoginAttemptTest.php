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
}
