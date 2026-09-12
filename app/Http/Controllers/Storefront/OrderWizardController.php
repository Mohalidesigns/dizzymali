<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Orders\RecalculateQuote;
use App\Actions\Orders\SaveDraftOrder;
use App\Actions\Orders\SubmitOrder;
use App\Exceptions\OrderNotSubmittable;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDraftOrderRequest;
use App\Http\Resources\FabricVariantResource;
use App\Http\Resources\GarmentTypeResource;
use App\Http\Resources\MeasurementProfileResource;
use App\Http\Resources\OrderResource;
use App\Models\FabricMaterial;
use App\Models\FabricVariant;
use App\Models\GarmentType;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The six-step wizard. Thin by design: validate, delegate to an Action, return
 * a resource. Anything that looks like business logic in here is a bug.
 */
class OrderWizardController extends Controller
{
    public function __construct(
        private readonly SaveDraftOrder $saveDraft,
        private readonly RecalculateQuote $recalculate,
        private readonly SubmitOrder $submit,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $order = $this->saveDraft->currentDraft($user);

        $order->load([
            'items.garmentType', 'items.fabricVariant.fabric', 'items.options', 'items.inspirations',
            'shippingAddress',
        ]);

        return Inertia::render('storefront/OrderWizard', [
            'order' => new OrderResource($order),
            'garmentTypes' => GarmentTypeResource::collection(
                GarmentType::active()
                    ->with(['measurementFields', 'optionGroups.options'])
                    ->orderBy('sort_order')
                    ->get(),
            ),
            'fabricVariants' => FabricVariantResource::collection(
                FabricVariant::active()->with('fabric.material')->orderBy('price_per_yard_kobo')->get(),
            ),
            'materials' => FabricMaterial::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'slug', 'name']),
            'profiles' => MeasurementProfileResource::collection(
                $user->measurementProfiles()->with('values.measurementField')->latest()->get(),
            ),
            'addresses' => $user->addresses()->latest()->get(),
            'unitPreference' => $user->unit_preference ?? 'in',
        ]);
    }

    public function update(SaveDraftOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $this->saveDraft->handle($order, $request->validated());

        return back();
    }

    /** Live re-price for the wizard's price panel. Always recalculated here. */
    public function quote(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $quote = $this->recalculate->handle($order);

        return response()->json($quote->toArray());
    }

    public function submit(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('submit', $order);

        try {
            $this->submit->handle($order, $request->user());
        } catch (OrderNotSubmittable $e) {
            return back()->withErrors(['order' => $e->reasons]);
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Your order is in. We will confirm your measurements before cutting.');
    }
}
