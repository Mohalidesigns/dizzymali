<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CustomerAdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FabricAdminController;
use App\Http\Controllers\Admin\GarmentTypeAdminController;
use App\Http\Controllers\Admin\MeasurementReviewController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Storefront\AddressController;
use App\Http\Controllers\Storefront\CatalogueController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\InspirationController;
use App\Http\Controllers\Storefront\MeasurementProfileController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\OrderWizardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront — public
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/garments', [CatalogueController::class, 'garments'])->name('garments.index');
Route::get('/garments/{garmentType:slug}', [CatalogueController::class, 'garment'])->name('garments.show');
Route::get('/fabrics', [CatalogueController::class, 'fabrics'])->name('fabrics.index');

/*
|--------------------------------------------------------------------------
| Storefront — signed in
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    // The six-step wizard.
    Route::get('/order', [OrderWizardController::class, 'show'])->name('order.wizard');
    Route::patch('/order/{order}', [OrderWizardController::class, 'update'])->name('order.update');
    Route::get('/order/{order}/quote', [OrderWizardController::class, 'quote'])->name('order.quote');
    Route::post('/order/{order}/submit', [OrderWizardController::class, 'submit'])->name('order.submit');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('/measurements', [MeasurementProfileController::class, 'index'])->name('measurements.index');
    Route::post('/measurements', [MeasurementProfileController::class, 'store'])->name('measurements.store');
    Route::patch('/measurements/{measurementProfile}', [MeasurementProfileController::class, 'update'])->name('measurements.update');
    Route::delete('/measurements/{measurementProfile}', [MeasurementProfileController::class, 'destroy'])->name('measurements.destroy');

    Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::patch('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

    Route::post('/order-items/{orderItem}/inspirations', [InspirationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('inspirations.store');
    Route::get('/inspirations/{inspiration}', [InspirationController::class, 'show'])->name('inspirations.show');
    Route::delete('/inspirations/{inspiration}', [InspirationController::class, 'destroy'])->name('inspirations.destroy');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
| Gated twice on purpose: the role middleware keeps the wrong people out of
| the section, and a Policy on every action keeps the right people inside
| their own lane.
*/

Route::middleware(['auth', 'verified', 'role:tailor|staff|admin|super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/orders', [OrderAdminController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderAdminController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/advance', [OrderAdminController::class, 'advance'])->name('orders.advance');
        Route::post('/orders/{order}/tailor', [OrderAdminController::class, 'assignTailor'])->name('orders.tailor');
        Route::post('/orders/{order}/note', [OrderAdminController::class, 'note'])->name('orders.note');

        Route::get('/fabrics', [FabricAdminController::class, 'index'])->name('fabrics.index');
        Route::post('/fabrics', [FabricAdminController::class, 'storeFabric'])->name('fabrics.store');
        Route::patch('/fabrics/{fabric}', [FabricAdminController::class, 'updateFabric'])->name('fabrics.update');
        Route::post('/fabric-variants', [FabricAdminController::class, 'storeVariant'])->name('variants.store');
        Route::patch('/fabric-variants/{fabricVariant}', [FabricAdminController::class, 'updateVariant'])->name('variants.update');

        Route::get('/garment-types', [GarmentTypeAdminController::class, 'index'])->name('garments.index');
        Route::patch('/garment-types/{garmentType}', [GarmentTypeAdminController::class, 'update'])->name('garments.update');
        Route::post('/garment-types/{garmentType}/yardage-rules', [GarmentTypeAdminController::class, 'storeYardageRule'])->name('garments.rules.store');
        Route::delete('/garment-types/{garmentType}/yardage-rules/{yardageRule}', [GarmentTypeAdminController::class, 'destroyYardageRule'])->name('garments.rules.destroy');

        Route::get('/measurement-reviews', [MeasurementReviewController::class, 'index'])->name('measurements.index');
        Route::post('/measurement-reviews/{measurementProfile}/approve', [MeasurementReviewController::class, 'approve'])->name('measurements.approve');
        Route::post('/measurement-reviews/{measurementProfile}/reject', [MeasurementReviewController::class, 'reject'])->name('measurements.reject');

        Route::get('/customers', [CustomerAdminController::class, 'index'])->name('customers.index');
        Route::get('/customers/{user}', [CustomerAdminController::class, 'show'])->name('customers.show');
    });

require __DIR__.'/auth.php';
