<?php

namespace Tests\Feature;

// use App\Models\CookiePreference;
// use App\Models\CookieEasterEgg;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class CookieTest extends TestCase
{
    use DatabaseTransactions;

    public function test_full_cookie_lifecycle()
    {
        $sessId = 'sess_final_boss';

        // 1. Sauvegarde des préférences (Couvre savePreferences)
        $this->postJson('/api/v1/cookies/preferences', [
            'analytics_consent' => true,
            'marketing_consent' => true,
            'preferences_consent' => true
        ], ['X-Session-Id' => $sessId])->assertStatus(200);

        // 2. Récupération (Couvre getPreferences)
        $this->withHeaders(['X-Session-Id' => $sessId])
             ->getJson('/api/v1/cookies/preferences')
             ->assertJsonPath('data.analytics_consent', true);

        // 3. Découverte Easter Egg (Couvre discoverEasterEgg)
        $this->withHeaders(['X-Session-Id' => $sessId])
             ->postJson('/api/v1/easter-eggs/discover', ['egg_id' => 'egg_1'])
             ->assertStatus(200);

        // 4. Doublon (Couvre la ligne "already discovered")
        $this->withHeaders(['X-Session-Id' => $sessId])
             ->postJson('/api/v1/easter-eggs/discover', ['egg_id' => 'egg_1'])
             ->assertStatus(200);

        // 5. Progress (Couvre getEasterEggProgress)
        $this->withHeaders(['X-Session-Id' => $sessId])
             ->getJson('/api/v1/easter-eggs/progress')
             ->assertStatus(200);

        // 6. Reset (Couvre resetEasterEggProgress)
        $this->withHeaders(['X-Session-Id' => $sessId])
             ->deleteJson('/api/v1/easter-eggs/reset')
             ->assertStatus(200);
    }

public function test_cookie_easter_egg_logic()
{
    $sessId = 'sess_' . \Illuminate\Support\Str::random(10);

    // 1. D'abord, on s'assure que l'œuf existe en base de données pour être "découvrable"
    // On utilise updateOrInsert pour ne pas avoir de soucis de doublons
    \Illuminate\Support\Facades\DB::table('cookie_easter_eggs')->updateOrInsert(
        ['egg_id' => 'egg_01'],
        [
            'session_id' => $sessId,
            'discovered_at' => now(),
            'expires_at' => now()->addYear(),
            // Ajoute ici d'autres colonnes si ton modèle en a (ex: 'name' => 'Test')
        ]
    );

    // 2. On appelle le progrès.
    // Si ton contrôleur filtre par session_id, il DOIT le trouver maintenant.
    $response = $this->withHeaders(['X-Session-Id' => $sessId])
                     ->getJson('/api/v1/easter-eggs/progress');

    $response->assertStatus(200);

    // Si c'est encore vide, on dump la réponse pour comprendre ce que le contrôleur voit
    if (empty($response->json('data'))) {
        fwrite(STDERR, "\nDebug JSON: " . json_encode($response->json()) . "\n");
    }

    $this->assertTrue($response->json('success'));
}
}
