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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_import_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ledger_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16);
            $table->date('timestamp');
            $table->date('paid_timestamp');
            $table->decimal('quantity', 20, 8)->nullable();
            $table->decimal('price', 30, 15)->nullable();
            $table->decimal('total', 30, 15);
            $table->char('currency', 3);
            $table->decimal('conversion_rate', 20, 10)->nullable();
            $table->decimal('converted_total', 30, 15)->nullable();
            $table->string('category')->nullable();
            $table->string('format')->nullable();
            $table->string('usage_type')->nullable();
            $table->char('region', 2)->nullable();
            $table->string('provider')->nullable();
            $table->string('retailer')->nullable();
            $table->string('external_id')->nullable();
            $table->string('source')->nullable();
            $table->string('importer_source')->nullable();
            $table->string('memo', 1000)->nullable();
            $table->boolean('published')->default(true);
            $table->boolean('billable')->default(true);
            $table->char('fingerprint', 40)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->index(['label_id', 'paid_timestamp', 'type']);
            $table->index(['label_id', 'timestamp']);
            $table->index(['label_id', 'fingerprint']);
            $table->index(['label_id', 'importer_source', 'external_id']);
            $table->index(['label_id', 'retailer']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
