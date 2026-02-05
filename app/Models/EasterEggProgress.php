<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for easter egg discovery tracking.
 * 
 * Tracks which easter eggs have been discovered by each session.
 */
class EasterEggProgress extends Model
{
    public $timestamps = false;

    protected $table = 'easter_egg_progress';

    protected $fillable = [
        'session_id',
        'egg_id',
        'discovered_at',
        'metadata'
    ];

    protected $casts = [
        'discovered_at' => 'datetime',
        'metadata' => 'array'
    ];
}
