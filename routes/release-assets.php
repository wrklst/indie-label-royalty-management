<?php

use App\Http\Controllers\ReleaseExportController;
use App\Http\Controllers\ReleaseFileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'label', 'label.admin'])->group(function () {
    Route::get('release-metadata', [ReleaseExportController::class, 'metadata'])->name('releases.metadata');
    Route::get('releases/{release}/package', [ReleaseExportController::class, 'package'])->name('releases.package');
    Route::get('releases/{release}/subvert-package', [ReleaseExportController::class, 'subvertPackage'])->name('releases.subvert-package');
    Route::get('releases/{release}/bandcamp-package', [ReleaseExportController::class, 'bandcampPackage'])->name('releases.bandcamp-package');
    Route::get('releases/{release}/zebralution-package', [ReleaseExportController::class, 'zebralutionPackage'])->name('releases.zebralution-package');

    Route::post('releases/{release}/files', [ReleaseFileController::class, 'store'])->name('release-files.store');
    Route::get('release-files/{release_file}', [ReleaseFileController::class, 'show'])->name('release-files.show');
    Route::patch('release-files/{release_file}', [ReleaseFileController::class, 'update'])->name('release-files.update');
    Route::delete('release-files/{release_file}', [ReleaseFileController::class, 'destroy'])->name('release-files.destroy');
    Route::post('release-files/{release_file}/preview', [ReleaseFileController::class, 'preview'])->name('release-files.preview');
    Route::get('releases/{release}/file-previews', [ReleaseFileController::class, 'previews'])->name('release-files.previews');
});
