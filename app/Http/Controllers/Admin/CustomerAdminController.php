<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeasurementProfileResource;
use App\Http\Resources\MoneyResource;
use App\Http\Resources\OrderResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $paid = array_map(
            fn (OrderStatus $s) => $s->value,
            array_filter(OrderStatus::cases(), fn (OrderStatus $s) => $s->isPaid()),
        );

        $query = User::query()
            ->role('customer')
            ->withCount('orders')
            ->withSum(['orders as lifetime_value_kobo' => fn ($q) => $q->whereIn('status', $paid)], 'total_kobo');

        if ($search = $request->string('search')->toString()) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        return Inertia::render('admin/Customers', [
            'customers' => $query->latest()->paginate(25)->withQueryString()
                ->through(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'country_code' => $u->country_code,
                    'orders_count' => $u->orders_count,
                    'lifetime_value' => MoneyResource::make((int) ($u->lifetime_value_kobo ?? 0)),
                    'created_at' => $u->created_at?->toDateString(),
                ]),
            'filters' => $request->only('search'),
        ]);
    }

    public function show(User $user): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('admin/CustomerDetail', [
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country_code' => $user->country_code,
                'preferred_currency' => $user->preferred_currency,
                'created_at' => $user->created_at?->toDateString(),
            ],
            'profiles' => MeasurementProfileResource::collection(
                $user->measurementProfiles()->with('values.measurementField')->get(),
            ),
            'orders' => OrderResource::collection(
                $user->orders()->with('items.garmentType')->latest()->get(),
            ),
            'addresses' => $user->addresses,
        ]);
    }
}
