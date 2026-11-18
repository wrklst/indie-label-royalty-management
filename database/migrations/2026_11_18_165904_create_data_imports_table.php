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
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('importer');
            $table->string('name');
            $table->string('status')->default('pending');
            $table->string('original_filename')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->date('default_timestamp')->nullable();
            $table->boolean('replace_timestamp')->default(false);
            $table->date('default_paid_timestamp')->nullable();
            $table->boolean('replace_paid_timestamp')->default(false);
            $table->boolean('needs_cleanup')->default(false);
            $table->json('tags')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('rows_count')->default(0);
            $table->unsignedInteger('transactions_count')->default(0);
            $table->unsignedInteger('duplicates_count')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->unsignedInteger('created_releases_count')->default(0);
            $table->unsignedInteger('created_tracks_count')->default(0);
            $table->decimal('total', 30, 15)->default(0);
            $table->json('errors')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['label_id', 'file_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_imports');
    }
};
