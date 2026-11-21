<?php

use App\Http\Controllers\Settings\BandcampController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'label', 'label.admin', 'service:bandcamp'])->group(function () {
    Route::get('settings/bandcamp', [BandcampController::class, 'edit'])->name('bandcamp.edit');
    Route::put('settings/bandcamp', [BandcampController::class, 'update'])->name('bandcamp.update');
    Route::delete('settings/bandcamp', [BandcampController::class, 'destroy'])->name('bandcamp.destroy');
    Route::post('settings/bandcamp/connect', [BandcampController::class, 'connect'])->name('bandcamp.connect');
    Route::post('settings/bandcamp/bands', [BandcampController::class, 'bands'])->name('bandcamp.bands');
    Route::post('settings/bandcamp/sync', [BandcampController::class, 'sync'])->name('bandcamp.sync');
});
