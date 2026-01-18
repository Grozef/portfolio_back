<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modele ContactMessage - Represente un message de contact.
 *
 * Stocke les messages envoyes via le formulaire de contact du portfolio.
 *
 * @package App\Models
 *
 * @property int $id
 * @property string $name Nom de l'expediteur
 * @property string $email Email de l'expediteur
 * @property string|null $subject Sujet du message
 * @property string $message Contenu du message
 * @property \Carbon\Carbon|null $read_at Date de lecture
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ContactMessage extends Model
{
    /**
     * Attributs assignables en masse.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'read_at',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Scope: Messages non lus.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope: Messages lus.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }
}