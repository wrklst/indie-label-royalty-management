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
        Schema::create('airplay_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('airplay_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('new');
            $table->boolean('is_radio')->default(false);
            $table->string('station')->nullable();
            $table->string('url', 2000);
            $table->char('url_hash', 40);
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('domain')->nullable();
            $table->string('query', 700);
            $table->timestamps();
            $table->unique(['track_id', 'url_hash']);
            $table->index(['label_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airplay_findings');
    }
};
