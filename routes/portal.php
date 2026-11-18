<?php

use App\Http\Controllers\Auth\LoginCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login/code', [LoginCodeController::class, 'create'])->name('login.code.create');
    Route::post('login/code', [LoginCodeController::class, 'store'])->name('login.code.store');
    Route::get('login/code/verify', [LoginCodeController::class, 'verify'])->name('login.code.verify');
    Route::post('login/code/verify', [LoginCodeController::class, 'authenticate'])->name('login.code.authenticate');
});
