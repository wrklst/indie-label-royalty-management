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
        Schema::create('airplay_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('airplay_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query', 700);
            $table->char('region', 2)->nullable();
            $table->boolean('succeeded');
            $table->unsignedInteger('new_findings')->default(0);
            $table->string('error', 500)->nullable();
            $table->json('results')->nullable();
            $table->timestamps();
            $table->index(['label_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airplay_searches');
    }
};
