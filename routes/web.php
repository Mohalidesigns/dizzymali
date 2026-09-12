<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CmsAdminController;
use App\Http\Controllers\Admin\CurrencyAdminController;
use App\Http\Controllers\Admin\CustomerAdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FabricAdminController;
use App\Http\Controllers\Admin\GarmentTypeAdminController;
use App\Http\Controllers\Admin\MeasurementReviewController;
use App\Http\Controllers\Admin\MediaAdminController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\PaymentAdminController;
use App\Http\Controllers\Admin\ProgressPhotoController;
use App\Http\Controllers\Admin\ShippingAdminController;
use App\Http\Controllers\Storefront\AddressController;
use App\Http\Controllers\Storefront\CatalogueController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\InspirationController;
use App\Http\Controllers\Storefront\MeasurementProfileController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\OrderWizardController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Controllers\WebhookController;
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

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Gateway webhooks
|--------------------------------------------------------------------------
| No session, no CSRF token, no authentication — a gateway has none of those.
| The signature check inside each gateway is the only thing standing between
| this endpoint and a stranger marking orders paid, which is why it is done
| first and in constant time.
*/

Route::post('/webhooks/{gateway}', WebhookController::class)
    ->middleware('throttle:120,1')
    ->withoutMiddleware([Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->name('webhooks.receive');

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

    Route::get('/checkout/{order}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/{order}/pay', [CheckoutController::class, 'pay'])
        ->middleware('throttle:10,1')
        ->name('checkout.pay');
    Route::get('/payments/{payment}/return', [CheckoutController::class, 'return'])->name('payments.return');

    Route::get('/progress-photos/{progressPhoto}', [ProgressPhotoController::class, 'show'])
        ->name('progress-photos.show');

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

        Route::post('/orders/{order}/progress-photos', [ProgressPhotoController::class, 'store'])->name('orders.photos.store');
        Route::delete('/progress-photos/{progressPhoto}', [ProgressPhotoController::class, 'destroy'])->name('photos.destroy');
        Route::post('/orders/{order}/ship', [ShippingAdminController::class, 'ship'])->name('orders.ship');
        Route::post('/orders/{order}/record-payment', [PaymentAdminController::class, 'recordManual'])->name('orders.record-payment');

        Route::get('/payments', [PaymentAdminController::class, 'index'])->name('payments.index');
        Route::post('/payments/{payment}/refund', [PaymentAdminController::class, 'refund'])->name('payments.refund');

        Route::get('/media', [MediaAdminController::class, 'index'])->name('media.index');
        Route::post('/media', [MediaAdminController::class, 'store'])->name('media.store');
        Route::get('/media/{mediaAsset}/preview', [MediaAdminController::class, 'preview'])->name('media.preview');
        Route::post('/media/{mediaAsset}/retry', [MediaAdminController::class, 'retry'])->name('media.retry');
        Route::delete('/media/{mediaAsset}', [MediaAdminController::class, 'destroy'])->name('media.destroy');

        Route::get('/cms', [CmsAdminController::class, 'index'])->name('cms.index');
        Route::post('/cms', [CmsAdminController::class, 'store'])->name('cms.store');
        Route::patch('/cms/{cmsBlock}', [CmsAdminController::class, 'update'])->name('cms.update');
        Route::delete('/cms/{cmsBlock}', [CmsAdminController::class, 'destroy'])->name('cms.destroy');
        Route::post('/cms/reorder', [CmsAdminController::class, 'reorder'])->name('cms.reorder');

        Route::get('/shipping', [ShippingAdminController::class, 'index'])->name('shipping.index');
        Route::post('/shipping/zones/{shippingZone}/rates', [ShippingAdminController::class, 'storeRate'])->name('shipping.rates.store');
        Route::patch('/shipping/rates/{shippingRate}', [ShippingAdminController::class, 'updateRate'])->name('shipping.rates.update');
        Route::delete('/shipping/rates/{shippingRate}', [ShippingAdminController::class, 'destroyRate'])->name('shipping.rates.destroy');

        Route::get('/currencies', [CurrencyAdminController::class, 'index'])->name('currencies.index');
        Route::patch('/currencies/{currency}', [CurrencyAdminController::class, 'update'])->name('currencies.update');
        Route::post('/currencies/{currency}/rates', [CurrencyAdminController::class, 'storeRate'])->name('currencies.rates.store');

        Route::get('/customers', [CustomerAdminController::class, 'index'])->name('customers.index');
        Route::get('/customers/{user}', [CustomerAdminController::class, 'show'])->name('customers.show');
    });

require __DIR__.'/auth.php';
