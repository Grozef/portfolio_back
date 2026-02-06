<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cookie Easter Egg Model
 *
 * Stores easter egg discoveries for analytics
 * Uses cookie-based session tracking
 * Auto-expires after 1 year
 */
class CookieEasterEgg extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'egg_id',
        'discovered_at',
        'expires_at',
        'metadata'
    ];

    protected $casts = [
        'discovered_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Boot method - set expiration date automatically
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->discovered_at) {
                $model->discovered_at = now();
            }
            if (!$model->expires_at) {
                $model->expires_at = now()->addYear();
            }
        });
    }

    /**
     * Check if record has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Scope to get non-expired records
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
