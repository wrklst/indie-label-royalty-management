<?php

use App\Http\Controllers\RoyaltyTools\MechanicalsController;
use App\Http\Controllers\RoyaltyTools\PpdController;
use App\Http\Controllers\RoyaltyTools\RatesController;
use App\Http\Controllers\RoyaltyTools\ReservesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'label', 'label.admin'])->prefix('royalty-tools')->name('royalty-tools.')->group(function () {
    Route::get('mechanicals', [MechanicalsController::class, 'index'])->name('mechanicals.index');
    Route::post('mechanicals', [MechanicalsController::class, 'store'])->name('mechanicals.store');

    Route::get('ppd', [PpdController::class, 'index'])->name('ppd.index');
    Route::post('ppd', [PpdController::class, 'store'])->name('ppd.store');

    Route::get('reserves', [ReservesController::class, 'index'])->name('reserves.index');
    Route::post('reserves', [ReservesController::class, 'store'])->name('reserves.store');

    Route::get('{tool}/rates/{release}', [RatesController::class, 'edit'])->name('rates.edit')->whereIn('tool', ['mechanicals', 'ppd']);
    Route::put('{tool}/rates/{release}', [RatesController::class, 'update'])->name('rates.update')->whereIn('tool', ['mechanicals', 'ppd']);
});
