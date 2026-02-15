<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Modele Book - Represente un livre dans la bibliotheque du portfolio.
 *
 * Les donnees du livre peuvent provenir de deux sources:
 * - API Open Library (via ISBN) avec mise en cache
 * - Saisie manuelle (fallback si ISBN non trouve)
 *
 * @package App\Models
 *
 * @property int $id
 * @property string|null $isbn ISBN-10 ou ISBN-13
 * @property string $title Titre du livre
 * @property string|null $author Auteur(s)
 * @property string|null $genre Genre du livre
 * @property string|null $cover_url URL de la couverture
 * @property string $status Statut de lecture (read, reading, to-read)
 * @property int|null $rating Note personnelle (1-5)
 * @property string|null $review Avis personnel
 * @property bool $is_featured Mis en avant sur la page
 * @property int $sort_order Ordre d'affichage
 * @property array|null $cached_data Donnees cachees depuis Open Library
 * @property \Carbon\Carbon|null $cached_at Date du dernier cache
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Book extends Model
{
    /**
     * Attributs assignables en masse.
     *
     * @var array<string>
     */
    protected $fillable = [
        'isbn',
        'title',
        'author',
        'genre',
        'cover_url',
        'status',
        'rating',
        'review',
        'is_featured',
        'sort_order',
        'cached_data',
        'cached_at',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rating' => 'integer',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'cached_data' => 'array',
        'cached_at' => 'datetime',
    ];

    /**
     * Attributs ajoutes au JSON.
     *
     * @var array<string>
     */
    protected $appends = [
        'display_title',
        'display_author',
        'display_cover_url',
        'description',
    ];

    /**
     * Accesseur pour le titre affiche.
     * Priorite: cached_data > title
     *
     * @return Attribute
     */
    protected function displayTitle(): Attribute
    {
        return Attribute::get(fn() => $this->cached_data['title'] ?? $this->title);
    }

    /**
     * Accesseur pour l'auteur affiche.
     * Priorite: cached_data > author
     *
     * @return Attribute
     */
    protected function displayAuthor(): Attribute
    {
        return Attribute::get(fn() => $this->cached_data['author'] ?? $this->author);
    }

    /**
     * Accesseur pour l'URL de couverture affichee.
     * Priorite: cached_data > cover_url
     *
     * @return Attribute
     */
    protected function displayCoverUrl(): Attribute
    {
        return Attribute::get(fn() => $this->cached_data['cover_url'] ?? $this->cover_url);
    }

    /**
     * Accesseur pour la description (depuis le cache uniquement).
     *
     * @return Attribute
     */
    protected function description(): Attribute
    {
        return Attribute::get(fn() => $this->cached_data['description'] ?? null);
    }

    /**
     * Scope: Livres mis en avant.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: Filtrer par statut de lecture.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status Statut (read, reading, to-read)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Tri par ordre puis par date.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderByDesc('created_at');
    }

    /**
     * Verifie si le cache doit etre rafraichi.
     * Le cache expire apres 30 jours.
     *
     * @return bool True si le cache est expire ou inexistant
     */
    public function needsCacheRefresh(): bool
    {
        if (!$this->isbn || !$this->cached_at) {
            return (bool) $this->isbn;
        }

        return $this->cached_at->diffInDays(now()) > 30;
    }

    /**
     * Met a jour les donnees cachees.
     *
     * @param array $data Donnees a mettre en cache
     * @return void
     */
    public function updateCache(array $data): void
    {
        $this->update([
            'cached_data' => $data,
            'cached_at' => now(),
        ]);
    }
}
