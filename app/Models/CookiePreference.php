<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * Cookie Preference Model
 *
 * Stores GDPR-compliant cookie consent preferences
 * Auto-expires after 1 year
 */
class CookiePreference extends Model
{
    protected $fillable = [
        'session_id',
        'analytics_consent',
        'marketing_consent',
        'preferences_consent',
        'consent_date',
        'expires_at',
        'metadata'
    ];

    protected $casts = [
        'analytics_consent' => 'boolean',
        'marketing_consent' => 'boolean',
        'preferences_consent' => 'boolean',
        'consent_date' => 'datetime',
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
            if (!$model->consent_date) {
                $model->consent_date = now();
            }
            if (!$model->expires_at) {
                $model->expires_at = now()->addYear();
            }
        });
    }

    /**
     * Check if preference has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Scope to get non-expired preferences
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Renew consent for another year
     */
    public function renew(): void
    {
        $this->consent_date = now();
        $this->expires_at = now()->addYear();
        $this->save();
    }
}
