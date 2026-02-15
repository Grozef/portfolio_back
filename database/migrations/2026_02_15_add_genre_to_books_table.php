<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('genre')->nullable()->after('author');
            // enum ?
            // $table->enum('genre', ['Fantasy', 'Sci-Fi', 'Mystery', 'Romance', 'History', 'Biography', 'Other'])->nullable()->after('author');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('genre');
        });
    }
};
