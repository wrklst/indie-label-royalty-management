<?php

use App\Http\Controllers\AirplayController;
use App\Http\Controllers\AirplayResearchController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\HandbookController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\PayeeController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\Settings\LabelSettingsController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('labels/create', [LabelController::class, 'create'])->name('labels.create');
    Route::post('labels', [LabelController::class, 'store'])->name('labels.store');
    Route::post('labels/{label}/switch', [LabelController::class, 'switch'])->name('labels.switch');

    Route::middleware('label')->group(function () {
        Route::get('my-statements', [PortalController::class, 'index'])->name('portal.index');
        Route::get('my-statements/{statement}', [PortalController::class, 'show'])->name('portal.show')->whereNumber('statement');
        Route::get('my-statements/{statement}/download', [PortalController::class, 'download'])->name('portal.download')->whereNumber('statement');

        Route::middleware('label.admin')->group(function () {
            Route::get('dashboard', DashboardController::class)->name('dashboard');

            Route::get('handbook/{slug}/panel', [HandbookController::class, 'panel'])->where('slug', '[\w-]+')->name('handbook.panel');
            Route::get('handbook/{slug?}', [HandbookController::class, 'show'])->where('slug', '[\w-]+')->name('handbook');

            Route::get('catalog', [CatalogController::class, 'index'])->name('catalog.index');
            Route::patch('catalog/assign', [CatalogController::class, 'assign'])->name('catalog.assign');

            Route::resource('artists', ArtistController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::resource('releases', ReleaseController::class);
            Route::resource('tracks', TrackController::class)->only(['index', 'show', 'update', 'destroy']);
            Route::resource('items', ItemController::class)->only(['index', 'update', 'destroy']);
            Route::resource('contracts', ContractController::class)->except(['show']);
            Route::resource('payees', PayeeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::resource('ledgers', LedgerController::class)->only(['index', 'store', 'update', 'destroy']);

            Route::resource('airplays', AirplayController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::post('airplays/{airplay}/links', [AirplayController::class, 'storeLink'])->name('airplays.links.store');
            Route::delete('airplays/{airplay}/links', [AirplayController::class, 'destroyLink'])->name('airplays.links.destroy');
            Route::post('airplays/research', [AirplayResearchController::class, 'store'])->name('airplays.research.store');
            Route::post('airplay-findings/{finding}/keep', [AirplayResearchController::class, 'keep'])->name('airplay-findings.keep');
            Route::post('airplay-findings/{finding}/dismiss', [AirplayResearchController::class, 'dismiss'])->name('airplay-findings.dismiss');
            Route::post('airplays/{airplay}/research', [AirplayResearchController::class, 'airplay'])->name('airplays.research.airplay');
            Route::post('airplays/{airplay}/search', [AirplayController::class, 'search'])->middleware('throttle:30,1')->name('airplays.search');

            Route::resource('transactions', TransactionController::class)->except(['show']);

            Route::get('data-imports/template', [DataImportController::class, 'template'])->name('data-imports.template');
            Route::post('data-imports/{data_import}/resolve', [DataImportController::class, 'resolve'])->name('data-imports.resolve');
            Route::resource('data-imports', DataImportController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

            Route::resource('periods', PeriodController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
            Route::get('periods/{period}/payees/{payee}', [PeriodController::class, 'payee'])->name('periods.payee');
            Route::post('periods/{period}/statements', [PeriodController::class, 'statements'])->name('periods.statements');
            Route::post('periods/{period}/payouts', [PeriodController::class, 'payout'])->name('periods.payout');

            Route::resource('statements', StatementController::class)->only(['index', 'show', 'destroy']);
            Route::post('statements/{statement}/publish', [StatementController::class, 'publish'])->name('statements.publish');
            Route::get('statements/{statement}/download', [StatementController::class, 'download'])->name('statements.download');

            Route::resource('payouts', PayoutController::class)->only(['index', 'destroy']);

            Route::get('settings/label', [LabelSettingsController::class, 'edit'])->name('label-settings.edit');
            Route::put('settings/label', [LabelSettingsController::class, 'update'])->name('label-settings.update');
            Route::post('settings/territories', [LabelSettingsController::class, 'storeTerritory'])->name('territories.store');
            Route::delete('settings/territories/{territory}', [LabelSettingsController::class, 'destroyTerritory'])->name('territories.destroy');

            Route::get('settings/team', [TeamController::class, 'index'])->name('team.index');
            Route::post('settings/team', [TeamController::class, 'store'])->middleware('throttle:30,60')->name('team.store');
            Route::put('settings/team/{user}', [TeamController::class, 'update'])->name('team.update');
            Route::delete('settings/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
        });
    });
});

require __DIR__.'/portal.php';
require __DIR__.'/profitability.php';
require __DIR__.'/royalty-tools.php';
require __DIR__.'/bandcamp.php';
require __DIR__.'/release-assets.php';
require __DIR__.'/settings.php';
