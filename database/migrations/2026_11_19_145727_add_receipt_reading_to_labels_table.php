<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The AI service a label reads its receipts with. The key is encrypted.
     */
    public function up(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->string('ai_provider', 32)->nullable();
            $table->text('ai_key')->nullable();
            // Infomaniak: the product id. Other OpenAI-compatible services: their base URL.
            $table->string('ai_endpoint')->nullable();
            $table->string('ai_model')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->dropColumn(['ai_provider', 'ai_key', 'ai_endpoint', 'ai_model']);
        });
    }
};
