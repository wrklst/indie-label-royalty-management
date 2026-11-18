<?php

use App\Http\Controllers\ProfitabilityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'label', 'label.admin'])->group(function () {
    Route::get('profitability', [ProfitabilityController::class, 'index'])->name('profitability.index');
    Route::get('profitability/download', [ProfitabilityController::class, 'download'])->name('profitability.download');
});
