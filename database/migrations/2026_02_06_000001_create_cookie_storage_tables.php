<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cookie preferences and easter egg storage migration
 *
 * GDPR-compliant cookie storage for:
 * - User cookie preferences
 * - Easter egg discoveries (for analytics)
 * - 1-year expiration
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cookie_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 255)->unique()->index();
            $table->boolean('analytics_consent')->default(false);
            $table->boolean('marketing_consent')->default(false);
            $table->boolean('preferences_consent')->default(false);
            $table->timestamp('consent_date');
            $table->timestamp('expires_at'); // 1 year from consent_date
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });

        Schema::create('cookie_easter_eggs', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 255)->index();
            $table->string('egg_id', 50);
            $table->timestamp('discovered_at');
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at'); // 1 year from discovered_at

            $table->unique(['session_id', 'egg_id']);
            $table->index('discovered_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cookie_preferences');
        Schema::dropIfExists('cookie_easter_eggs');
    }
};
