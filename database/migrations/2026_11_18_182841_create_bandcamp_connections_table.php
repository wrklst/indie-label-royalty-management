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
        Schema::create('bandcamp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('client_id');
            $table->text('client_secret');
            $table->text('refresh_token')->nullable();
            $table->text('access_token')->nullable();
            $table->dateTime('access_token_expires_at')->nullable();
            $table->json('bands')->nullable();
            $table->unsignedBigInteger('band_id')->nullable();
            $table->unsignedBigInteger('member_band_id')->nullable();
            $table->boolean('enabled')->default(false);
            $table->date('sync_from')->nullable();
            $table->dateTime('last_synced_until')->nullable();
            $table->dateTime('last_sync_at')->nullable();
            $table->string('last_sync_status', 16)->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bandcamp_connections');
    }
};
