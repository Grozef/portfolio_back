<?php

namespace Tests\Unit;

use App\Models\CookieEasterEgg;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EasterEggTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cookie_easter_egg_auto_sets_expiration_on_creation()
    {
        $egg = CookieEasterEgg::create([
            'session_id' => 'test_session',
            'egg_id' => 'konami_code'
        ]);

        // Vérifie que le boot() a bien fonctionné
        $this->assertNotNull($egg->expires_at);
        $this->assertNotNull($egg->discovered_at);
        $this->assertTrue($egg->expires_at->isFuture());
    }
}
