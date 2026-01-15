<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration initiale pour la creation de toutes les tables du portfolio.
 * 
 * Tables creees:
 * - books: Stockage des livres avec cache des donnees Open Library
 * - contact_messages: Messages de contact recus via le formulaire
 * - login_attempts: Suivi des tentatives de connexion pour protection brute force
 *
 * @package Database\Migrations
 */
return new class extends Migration
{
    /**
     * Execute les migrations.
     * 
     * Cree les tables books, contact_messages et login_attempts
     * avec leurs index et contraintes.
     *
     * @return void
     */
    public function up(): void
    {
        // Table des livres
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('isbn', 13)->nullable()->unique();  // ISBN-10 ou ISBN-13, nullable si saisie manuelle
            $table->string('title');                            // Titre du livre (requis pour fallback)
            $table->string('author')->nullable();               // Auteur (fallback)
            $table->string('cover_url')->nullable();            // URL de la couverture (fallback)
            $table->string('status')->default('to-read');       // read, reading, to-read
            $table->tinyInteger('rating')->nullable();          // Note personnelle 1-5
            $table->text('review')->nullable();                 // Avis personnel
            $table->boolean('is_featured')->default(false);     // Mise en avant
            $table->integer('sort_order')->default(0);          // Ordre d'affichage
            $table->json('cached_data')->nullable();            // Donnees Open Library en cache
            $table->timestamp('cached_at')->nullable();         // Date du dernier cache
            $table->timestamps();
            
            $table->index('status');
            $table->index('is_featured');
            $table->index(['sort_order', 'created_at']);
        });

        // Table des messages de contact
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');                             // Nom de l'expediteur
            $table->string('email');                            // Email de l'expediteur
            $table->string('subject')->nullable();              // Sujet du message
            $table->text('message');                            // Contenu du message
            $table->boolean('is_read')->default(false);         // Marque comme lu
            $table->timestamps();
            
            $table->index('is_read');
            $table->index('created_at');
        });

        // Table de suivi des tentatives de connexion (protection brute force)
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email');                            // Email tente
            $table->string('ip_address', 45);                   // Adresse IP (IPv4 ou IPv6)
            $table->boolean('successful')->default(false);      // Tentative reussie ou non
            $table->timestamp('attempted_at');                  // Date de la tentative
            
            $table->index(['email', 'attempted_at']);
            $table->index(['ip_address', 'attempted_at']);
        });
    }

    /**
     * Annule les migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('books');
    }
};
