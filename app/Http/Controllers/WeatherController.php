<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Exception;

class WeatherController extends Controller
{
    /**
     * Fetch current weather data from OpenWeatherMap API
     */
    public function __invoke(): JsonResponse
    {
        try {
            $data = Cache::remember('weather_data', 1800, function () {
                $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                    'lat'   => 48.85,
                    'lon'   => 2.35,
                    'units' => 'metric',
                    'appid' => config('services.weather.key'),
                ]);
                if ($response->successful()) {
                    return $response->json();
                }

                return null;
            });
            if (!$data) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Météo indisponible pour le moment'
                ], 503);
            }

            return response()->json($data);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur serveur lors de la récupération météo'
            ], 500);
        }
    }
}
