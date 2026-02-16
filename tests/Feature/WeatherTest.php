<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Crucial : sinon on teste le cache et pas le code du controller !
        Cache::flush();
    }

    public function test_weather_returns_data_successfully()
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'weather' => [['description' => 'clear sky']],
                'main' => ['temp' => 20]
            ], 200)
        ]);

        $response = $this->getJson('/api/v1/weather');

        $response->assertStatus(200)
                 ->assertJsonPath('main.temp', 20);
    }

    /**
     * Teste le retour null de la closure (Ligne 31-37 du controller)
     */
    public function test_weather_returns_503_when_api_fails()
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response([], 500)
        ]);

        $response = $this->getJson('/api/v1/weather');

        $response->assertStatus(503)
                 ->assertJsonPath('status', 'error')
                 ->assertJsonPath('message', 'Météo indisponible pour le moment');
    }

    /**
     * Teste le bloc catch (Exception) (Ligne 41-45 du controller)
     */
    public function test_weather_returns_500_on_exception()
    {
        // On simule une explosion réseau pour déclencher le catch(Exception)
        Http::fake(function() {
            throw new \Exception("API Down");
        });

        $response = $this->getJson('/api/v1/weather');

        $response->assertStatus(500)
                 ->assertJsonPath('status', 'error')
                 ->assertJsonPath('message', 'Erreur serveur lors de la récupération météo');
    }
}
