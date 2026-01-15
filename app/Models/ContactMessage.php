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
 * @property bool $is_read Marque comme lu
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
        'is_read',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_read' => 'boolean',
    ];

    /**
     * Scope: Messages non lus.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
