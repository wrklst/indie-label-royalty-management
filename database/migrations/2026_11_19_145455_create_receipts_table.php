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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The cost the receipt belongs to. A receipt whose cost is deleted waits to be booked again.
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('status', 16)->default('new');
            // What was read off the receipt, as a proposal for the cost.
            $table->string('vendor')->nullable();
            $table->date('paid_on')->nullable();
            $table->decimal('amount', 30, 15)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('category')->nullable();
            $table->string('summary', 500)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['label_id', 'transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
