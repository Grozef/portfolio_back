<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CookieTest extends TestCase
{
    use DatabaseTransactions;

    public function test_save_preferences_returns_cookie()
    {
        $response = $this->postJson('/api/v1/cookies/preferences', [
            'analytics_consent' => true,
            'marketing_consent' => false,
            'preferences_consent' => true
        ]);

        $response->assertStatus(200)
                 ->assertCookie('session_id')
                 ->assertJsonPath('data.analytics_consent', true);
    }

    public function test_get_statistics_works()
    {
        $response = $this->getJson('/api/v1/easter-eggs/statistics');
        $response->assertStatus(200)->assertJsonStructure(['success', 'data' => ['total_eggs', 'discovered']]);
    }
}
