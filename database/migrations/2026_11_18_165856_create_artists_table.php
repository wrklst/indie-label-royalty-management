<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alias_of_id')->nullable()->constrained('artists')->nullOnDelete();
            $table->string('name');
            $table->string('image_path')->nullable();
            $table->string('spotify_id')->nullable();
            $table->string('discogs_search_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['label_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
