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
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('catalog_number')->nullable();
            $table->string('upc', 32)->nullable();
            $table->string('image_path')->nullable();
            $table->date('released_on')->nullable();
            $table->string('spotify_id')->nullable();
            $table->string('discogs_id')->nullable();
            $table->boolean('accounts_mechanicals')->default(false);
            $table->decimal('mechanicals_rate', 12, 6)->nullable();
            $table->decimal('ppd_rate', 12, 6)->nullable();
            $table->decimal('reserves_rate', 12, 6)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['label_id', 'upc']);
            $table->index(['label_id', 'catalog_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
