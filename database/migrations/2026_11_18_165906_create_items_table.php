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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('data_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format_group')->nullable();
            $table->boolean('is_additionals')->default(false);
            $table->string('name');
            $table->string('artist_name')->nullable();
            $table->string('track_name')->nullable();
            $table->string('catalog_number')->nullable();
            $table->string('isrc', 16)->nullable();
            $table->string('iswc', 16)->nullable();
            $table->string('upc', 32)->nullable();
            $table->timestamps();
            $table->index(['label_id', 'isrc']);
            $table->index(['label_id', 'upc']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
