<?php

use App\Http\Controllers\LabelCostController;
use App\Http\Controllers\ProfitabilityController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'label', 'label.admin'])->group(function () {
    Route::get('profitability', [ProfitabilityController::class, 'index'])->name('profitability.index');
    Route::get('profitability/download', [ProfitabilityController::class, 'download'])->name('profitability.download');

    Route::get('label-costs', [LabelCostController::class, 'index'])->name('label-costs.index');
    Route::post('label-costs', [LabelCostController::class, 'store'])->name('label-costs.store');
    Route::put('label-costs/{transaction}', [LabelCostController::class, 'update'])->whereNumber('transaction')->name('label-costs.update');
    Route::post('label-costs/recurring', [LabelCostController::class, 'storeRecurring'])->name('label-costs.recurring.store');
    Route::put('label-costs/recurring/{recurring_expense}', [LabelCostController::class, 'updateRecurring'])->name('label-costs.recurring.update');
    Route::delete('label-costs/recurring/{recurring_expense}', [LabelCostController::class, 'destroyRecurring'])->name('label-costs.recurring.destroy');

    Route::post('receipts', [ReceiptController::class, 'store'])->middleware('throttle:60,1')->name('receipts.store');
    Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::put('receipts/{receipt}', [ReceiptController::class, 'update'])->name('receipts.update');
    Route::post('receipts/{receipt}/read', [ReceiptController::class, 'read'])->middleware('throttle:30,1')->name('receipts.read');
    Route::delete('receipts/{receipt}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');
});
