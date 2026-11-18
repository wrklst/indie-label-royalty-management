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
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('isrc', 16)->nullable();
            $table->string('iswc', 16)->nullable();
            $table->unsignedSmallInteger('track_number')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->decimal('mechanicals_rate', 12, 6)->nullable();
            $table->decimal('ppd_rate', 12, 6)->nullable();
            $table->timestamps();
            $table->index(['label_id', 'isrc']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
