<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CookieEasterEggTest extends TestCase {
    use DatabaseTransactions;

    public function test_cookie_endpoints_structure() {
        $this->postJson('/api/v1/cookies/preferences', [
            'analytics_consent' => true, 'marketing_consent' => true, 'preferences_consent' => true
        ])->assertStatus(200)->assertCookie('session_id');

        $this->getJson('/api/v1/easter-eggs/statistics')->assertStatus(200)->assertJsonPath('success', true);
    }
}
