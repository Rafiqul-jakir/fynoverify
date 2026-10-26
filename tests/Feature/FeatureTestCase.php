<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Feature;

use Rafiquljakir\FynoVerify\Tests\TestCase;
use Illuminate\Support\Facades\Route;

abstract class FeatureTestCase extends TestCase
{
    /**
     * Registers a small, varied set of routes under the fvt/ prefix.
     */
    protected function registerRoutes(): void
    {
        Route::get('fvt/documents', fn () => 'ok')->name('fvt.documents.index');
        Route::post('fvt/documents', fn () => 'ok')->name('fvt.documents.store');
        Route::get('fvt/documents/{document}', fn () => 'ok')->name('fvt.documents.show')->middleware(['auth:sanctum']);
        Route::put('fvt/documents/{document}', fn () => 'ok')->name('fvt.documents.update')->middleware(['auth:sanctum', 'can:update,document']);
        Route::get('fvt/unnamed', fn () => 'ok');
        Route::get('fvt/opt/{id?}', fn () => 'ok')->where('id', '[0-9]+')->name('fvt.opt');
        Route::match(['GET', 'POST'], 'fvt/multi', fn () => 'ok')->name('fvt.multi');
        Route::domain('tenant.example.com')->get('fvt/dom', fn () => 'ok')->name('fvt.dom');
        Route::get('/', fn () => 'ok')->name('fvt.root');
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, array<string, mixed>>
     */
    protected function routesById(array $report): array
    {
        $byId = [];
        foreach ($report['routes'] as $route) {
            $byId[$route['id']] = $route;
        }

        return $byId;
    }
}
