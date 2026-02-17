<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'isbn' => $this->isbn,
            'display_title' => $this->display_title, // Accesseur
            'display_author' => $this->display_author, // Accesseur
            'genre' => $this->genre,
            'display_cover_url' => $this->display_cover_url, // Accesseur
            'description' => $this->description, // Accesseur
            'title' => $this->title,
            'author' => $this->author,
            'cover_url' => $this->cover_url,
            'status' => $this->status,
            'rating' => $this->rating,
            'review' => $this->review,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'source' => $this->cached_data['source'] ?? 'manual',
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
