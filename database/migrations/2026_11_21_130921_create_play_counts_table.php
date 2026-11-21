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
        Schema::create('play_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            // Empty when the file named a track the catalogue does not have.
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            // The lines of one uploaded file share a batch, so the file can be taken back.
            $table->string('batch', 26);
            $table->string('source_file');
            // A key of Platforms::NAMES.
            $table->string('platform', 32);
            $table->string('track_name');
            $table->string('artist_name')->nullable();
            $table->string('isrc', 12)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('plays');
            $table->unsignedBigInteger('listeners')->nullable();
            $table->timestamps();

            $table->index(['label_id', 'platform', 'track_id']);
            $table->index(['label_id', 'batch']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('play_counts');
    }
};
