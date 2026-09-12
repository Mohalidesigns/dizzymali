<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Address;
use App\Models\CmsBlock;
use App\Models\Currency;
use App\Models\Fabric;
use App\Models\FabricVariant;
use App\Models\GarmentOption;
use App\Models\GarmentOptionGroup;
use App\Models\GarmentType;
use App\Models\MeasurementProfile;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\User;
use App\Models\YardageRule;
use App\Policies\AddressPolicy;
use App\Policies\CatalogueAdminPolicy;
use App\Policies\MeasurementProfilePolicy;
use App\Policies\OrderItemPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Every model has a Policy. A model added without one should fail
        // loudly in development rather than default to allow.
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(OrderItem::class, OrderItemPolicy::class);
        Gate::policy(MeasurementProfile::class, MeasurementProfilePolicy::class);
        Gate::policy(Address::class, AddressPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);

        foreach ([
            GarmentType::class, YardageRule::class, Fabric::class, FabricVariant::class,
            GarmentOptionGroup::class, GarmentOption::class,
            MediaAsset::class, CmsBlock::class, Currency::class,
            ShippingZone::class, ShippingRate::class,
        ] as $model) {
            Gate::policy($model, CatalogueAdminPolicy::class);
        }

        Gate::define('viewAny', fn (User $user) => $user->isBackOffice());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->symbols()->uncompromised()
            : Password::min(8));

        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
