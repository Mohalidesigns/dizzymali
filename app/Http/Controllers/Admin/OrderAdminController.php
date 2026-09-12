<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\AdvanceOrderStage;
use App\Enums\OrderStatus;
use App\Exceptions\IllegalOrderTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdvanceOrderStageRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderAdminController extends Controller
{
    public function __construct(private readonly AdvanceOrderStage $advance) {}

    public function index(Request $request): Response
    {
        $query = Order::query()
            ->with(['user:id,name,email', 'items.garmentType:id,name', 'items.fabricVariant:id,colour_name,fabric_id'])
            ->whereNot('status', OrderStatus::Draft)
            ->latest('submitted_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(fn ($q) => $q
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
        }

        return Inertia::render('admin/Orders', [
            'orders' => OrderResource::collection($query->paginate(25)->withQueryString()),
            'statuses' => collect(OrderStatus::cases())
                ->map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('manage', $order);

        $order->load([
            'user', 'items.garmentType', 'items.fabricVariant.fabric', 'items.options',
            'items.inspirations', 'items.tailor:id,name', 'statusEvents.actor:id,name',
            'progressPhotos', 'payments', 'shipments', 'shippingAddress',
        ]);

        return Inertia::render('admin/OrderDetail', [
            'order' => new OrderResource($order),
            'allowedTransitions' => collect($order->status->allowedTransitions())
                ->map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
            'tailors' => User::role('tailor')->get(['id', 'name']),
        ]);
    }

    public function advance(AdvanceOrderStageRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('advanceStage', $order);

        try {
            $this->advance->handle(
                $order,
                OrderStatus::from($request->string('status')->toString()),
                $request->user(),
                $request->input('note'),
            );
        } catch (IllegalOrderTransition $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Stage updated.');
    }

    public function assignTailor(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('manage', $order);

        $validated = $request->validate([
            'tailor_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $order->items()->update(['tailor_id' => $validated['tailor_id']]);

        return back()->with('success', 'Tailor assigned.');
    }

    public function note(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('manage', $order);

        $validated = $request->validate([
            'internal_notes' => ['required', 'string', 'max:5000'],
        ]);

        $order->update($validated);

        return back();
    }
}
