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
        Schema::create('airplays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('data_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origin', 16);
            $table->string('track_name');
            $table->string('artist_name')->nullable();
            $table->string('isrc', 16)->nullable();
            $table->string('station');
            $table->string('media_type')->nullable();
            $table->char('region', 2)->nullable();
            $table->string('society')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('amount', 30, 15)->nullable();
            $table->json('links')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['label_id', 'station']);
            $table->index(['label_id', 'isrc']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airplays');
    }
};
