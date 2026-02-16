<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CookieEasterEggResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'egg_id' => $this->egg_id,
            'discovered_at' => $this->discovered_at->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
}
