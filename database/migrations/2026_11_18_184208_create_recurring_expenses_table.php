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
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('amount', 30, 15);
            $table->char('currency', 3);
            $table->decimal('conversion_rate', 20, 10)->nullable();
            $table->string('interval', 20);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('occurrences_booked')->default(0);
            $table->string('memo', 1000)->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_expense_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_expense_id');
        });

        Schema::dropIfExists('recurring_expenses');
    }
};
