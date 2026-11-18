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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('memo')->nullable();
            $table->timestamps();
            $table->index(['label_id', 'name']);
        });

        Schema::create('contract_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('child_contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->decimal('weight', 18, 9)->default(1);
            $table->timestamps();
            $table->unique(['parent_contract_id', 'child_contract_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_links');
        Schema::dropIfExists('contracts');
    }
};
