<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What opening the found page itself showed.
     */
    public function up(): void
    {
        Schema::table('airplay_findings', function (Blueprint $table) {
            $table->string('verification', 16)->nullable();
            $table->string('page_title', 500)->nullable();
            $table->string('show', 500)->nullable();
            $table->text('excerpt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airplay_findings', function (Blueprint $table) {
            $table->dropColumn(['verification', 'page_title', 'show', 'excerpt']);
        });
    }
};
