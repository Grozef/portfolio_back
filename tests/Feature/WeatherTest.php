<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    public function test_weather_returns_data()
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response(['weather' => [['description' => 'clear sky']], 'main' => ['temp' => 20]], 200)
        ]);

        $response = $this->getJson('/api/v1/weather');
        $response->assertStatus(200)->assertJsonPath('main.temp', 20);
    }
}
