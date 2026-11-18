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
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->char('currency', 3)->default('EUR');
            $table->string('contact_email')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('accounts_mechanicals')->default(false);
            $table->boolean('accounts_ppd')->default(false);
            $table->boolean('accounts_reserves')->default(false);
            $table->json('importer_settings')->nullable();
            $table->timestamps();
        });

        Schema::create('label_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('admin');
            $table->unsignedBigInteger('payee_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['label_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_user');
        Schema::dropIfExists('labels');
    }
};
