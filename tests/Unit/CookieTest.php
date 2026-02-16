<?php

namespace Tests\Unit;

use App\Models\CookiePreference;
use App\Models\CookieEasterEgg;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Carbon\Carbon;

class CookieTest extends TestCase
{
    use DatabaseTransactions;

    public function test_preference_is_expired_works()
    {
        $pref = new CookiePreference([
            'expires_at' => now()->subDay()
        ]);
        $this->assertTrue($pref->isExpired());

        $pref->expires_at = now()->addDay();
        $this->assertFalse($pref->isExpired());
    }

    public function test_easter_egg_auto_sets_expiration_on_creation()
    {
        $egg = CookieEasterEgg::create([
            'session_id' => 'abc',
            'egg_id' => 'hidden_pixel'
        ]);

        $this->assertNotNull($egg->expires_at);
        $this->assertTrue($egg->expires_at->isFuture());
    }

    public function test_valid_scope_filters_expired_cookies()
    {
        CookiePreference::create(['session_id' => '1', 'expires_at' => now()->subYear()]);
        CookiePreference::create(['session_id' => '2', 'expires_at' => now()->addYear()]);

        $this->assertEquals(1, CookiePreference::valid()->count());
    }
}
