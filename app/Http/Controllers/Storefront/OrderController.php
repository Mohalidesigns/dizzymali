<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        // Scoped to the signed-in user in the query. Never filtered client-side.
        $orders = $request->user()
            ->orders()
            ->with(['items.garmentType', 'items.fabricVariant'])
            ->latest()
            ->paginate(12);

        return Inertia::render('storefront/Orders', [
            'orders' => OrderResource::collection($orders),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load([
            'items.garmentType', 'items.fabricVariant.fabric', 'items.options', 'items.inspirations',
            'statusEvents', 'progressPhotos', 'payments', 'shipments',
        ]);

        return Inertia::render('storefront/OrderDetail', [
            'order' => new OrderResource($order),
        ]);
    }
}
