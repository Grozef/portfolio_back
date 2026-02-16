<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CookiePreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'analytics_consent' => $this->analytics_consent,
            'marketing_consent' => $this->marketing_consent,
            'preferences_consent' => $this->preferences_consent,
            'has_consent' => true,
            'consent_date' => $this->consent_date->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}
