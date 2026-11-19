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
        Schema::create('platform_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            // A link belongs to a release or to a track.
            $table->foreignId('release_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('url', 1000);
            // "manual" or how it was found: "lookup" by code, "sales", "guess", "search".
            $table->string('origin', 16);
            // A found link the user removed. It is kept so it is not found again.
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['label_id', 'release_id']);
            $table->index(['label_id', 'track_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_links');
    }
};
