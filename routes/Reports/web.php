<?php

use App\Http\Controllers\Web\Reports\ReportController;
use App\Services\Reports\ReportRegistry;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core HR Reports
|--------------------------------------------------------------------------
|
| One set of routes per report, generated from the registry so each route
| carries its own category permission. Exports also require report.export.
| Self-service (*_own) permissions grant none of these.
|
*/

Route::middleware(['auth', 'verified'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])
        ->middleware('can:report.view')
        ->name('index');

    foreach (ReportRegistry::REPORTS as $class) {
        $key = $class::key();
        $permission = $class::permission();

        Route::get($key, [ReportController::class, 'show'])
            ->middleware("can:{$permission}")
            ->defaults('report', $key)
            ->name("{$key}.show");

        foreach (['excel', 'pdf', 'print'] as $format) {
            Route::get("{$key}/{$format}", [ReportController::class, $format])
                ->middleware(["can:{$permission}", 'can:report.export'])
                ->defaults('report', $key)
                ->name("{$key}.{$format}");
        }
    }
});
