<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for easter eggs tracking system.
 * 
 * Stores user progress for discovered easter eggs with session tracking.
 *
 * @package Database\Migrations
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('easter_egg_progress', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 255)->index();
            $table->string('egg_id', 50);
            $table->timestamp('discovered_at');
            $table->json('metadata')->nullable();
            
            $table->unique(['session_id', 'egg_id']);
            $table->index('discovered_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('easter_egg_progress');
    }
};
