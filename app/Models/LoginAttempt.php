<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modele LoginAttempt - Suivi des tentatives de connexion.
 * 
 * Utilise pour la protection contre les attaques par force brute.
 * Enregistre chaque tentative de connexion avec son resultat.
 *
 * @package App\Models
 * 
 * @property int $id
 * @property string $email Email tente
 * @property string $ip_address Adresse IP de la tentative
 * @property bool $successful Tentative reussie ou non
 * @property \Carbon\Carbon $attempted_at Date de la tentative
 */
class LoginAttempt extends Model
{
    /**
     * Desactive les timestamps automatiques.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Attributs assignables en masse.
     *
     * @var array<string>
     */
    protected $fillable = [
        'email',
        'ip_address',
        'successful',
        'attempted_at',
    ];

    /**
     * Casts des attributs.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'successful' => 'boolean',
        'attempted_at' => 'datetime',
    ];

    /**
     * Nombre maximum de tentatives autorisees.
     *
     * @var int
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * Duree du blocage en minutes.
     *
     * @var int
     */
    public const LOCKOUT_MINUTES = 15;

    /**
     * Enregistre une tentative de connexion.
     *
     * @param string $email Email tente
     * @param string $ipAddress Adresse IP
     * @param bool $successful Tentative reussie
     * @return self
     */
    public static function record(string $email, string $ipAddress, bool $successful): self
    {
        return self::create([
            'email' => strtolower($email),
            'ip_address' => $ipAddress,
            'successful' => $successful,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Compte les tentatives echouees recentes pour un email/IP.
     *
     * @param string $email Email a verifier
     * @param string $ipAddress Adresse IP a verifier
     * @return int Nombre de tentatives echouees
     */
    public static function recentFailedAttempts(string $email, string $ipAddress): int
    {
        $since = now()->subMinutes(self::LOCKOUT_MINUTES);

        return self::where(function ($query) use ($email, $ipAddress) {
                $query->where('email', strtolower($email))
                      ->orWhere('ip_address', $ipAddress);
            })
            ->where('successful', false)
            ->where('attempted_at', '>=', $since)
            ->count();
    }

    /**
     * Verifie si l'email/IP est bloque.
     *
     * @param string $email Email a verifier
     * @param string $ipAddress Adresse IP a verifier
     * @return bool True si bloque
     */
    public static function isBlocked(string $email, string $ipAddress): bool
    {
        return self::recentFailedAttempts($email, $ipAddress) >= self::MAX_ATTEMPTS;
    }

    /**
     * Calcule le temps restant avant deblocage.
     *
     * @param string $email Email a verifier
     * @param string $ipAddress Adresse IP a verifier
     * @return int Secondes restantes (0 si non bloque)
     */
    public static function remainingLockoutSeconds(string $email, string $ipAddress): int
    {
        $lastAttempt = self::where(function ($query) use ($email, $ipAddress) {
                $query->where('email', strtolower($email))
                      ->orWhere('ip_address', $ipAddress);
            })
            ->where('successful', false)
            ->orderByDesc('attempted_at')
            ->first();

        if (!$lastAttempt) {
            return 0;
        }

        $unlockAt = $lastAttempt->attempted_at->addMinutes(self::LOCKOUT_MINUTES);
        
        if (now()->gte($unlockAt)) {
            return 0;
        }

        return now()->diffInSeconds($unlockAt);
    }

    /**
     * Efface les tentatives reussies pour un email (apres connexion reussie).
     *
     * @param string $email Email a nettoyer
     * @return void
     */
    public static function clearSuccessful(string $email): void
    {
        self::where('email', strtolower($email))
            ->where('successful', true)
            ->delete();
    }

    /**
     * Nettoie les anciennes tentatives (plus de 24h).
     * A appeler via une commande schedulee.
     *
     * @return int Nombre d'enregistrements supprimes
     */
    public static function cleanup(): int
    {
        return self::where('attempted_at', '<', now()->subDay())->delete();
    }
}
