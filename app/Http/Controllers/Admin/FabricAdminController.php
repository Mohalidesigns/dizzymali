<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FabricVariantResource;
use App\Models\Fabric;
use App\Models\FabricMaterial;
use App\Models\FabricVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FabricAdminController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Fabric::class);

        return Inertia::render('admin/Fabrics', [
            'fabrics' => Fabric::query()
                ->with(['material:id,name,slug', 'variants'])
                ->orderBy('sort_order')
                ->get(),
            'variants' => FabricVariantResource::collection(
                FabricVariant::query()->with('fabric.material')->orderBy('fabric_id')->get(),
            ),
            'materials' => FabricMaterial::orderBy('sort_order')->get(),
        ]);
    }

    public function storeFabric(Request $request): RedirectResponse
    {
        $this->authorize('create', Fabric::class);

        $validated = $request->validate([
            'fabric_material_id' => ['required', 'exists:fabric_materials,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'care_instructions' => ['nullable', 'string', 'max:2000'],
            'drape_notes' => ['nullable', 'string', 'max:2000'],
            'origin' => ['nullable', 'string', 'max:120'],
            'gsm' => ['nullable', 'integer', 'between:40,900'],
            'width_inches' => ['nullable', 'numeric', 'between:10,120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        Fabric::create($validated + ['slug' => Str::slug($validated['name'])]);

        return back()->with('success', 'Fabric added.');
    }

    public function updateFabric(Request $request, Fabric $fabric): RedirectResponse
    {
        $this->authorize('update', $fabric);

        $fabric->update($request->validate([
            'fabric_material_id' => ['sometimes', 'exists:fabric_materials,id'],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'care_instructions' => ['nullable', 'string', 'max:2000'],
            'drape_notes' => ['nullable', 'string', 'max:2000'],
            'origin' => ['nullable', 'string', 'max:120'],
            'gsm' => ['nullable', 'integer', 'between:40,900'],
            'width_inches' => ['nullable', 'numeric', 'between:10,120'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return back()->with('success', 'Fabric updated.');
    }

    public function storeVariant(Request $request): RedirectResponse
    {
        $this->authorize('create', FabricVariant::class);

        $validated = $request->validate([
            'fabric_id' => ['required', 'exists:fabrics,id'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('fabric_variants', 'sku')],
            'colour_name' => ['required', 'string', 'max:80'],
            'colour_hex' => ['nullable', 'string', 'size:7'],
            'pattern' => ['nullable', 'string', 'max:80'],
            // Typed in whole naira by a human; converted to kobo here and
            // stored as an integer from this point onwards.
            'price_per_yard_naira' => ['required', 'integer', 'between:1,100000000'],
            'stock_yards' => ['required', 'numeric', 'between:0,999999'],
            'low_stock_threshold_yards' => ['sometimes', 'numeric', 'between:0,9999'],
            'min_order_yards' => ['sometimes', 'numeric', 'between:0.25,50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['price_per_yard_kobo'] = $validated['price_per_yard_naira'] * 100;
        unset($validated['price_per_yard_naira']);

        FabricVariant::create($validated);

        return back()->with('success', 'Variant added.');
    }

    public function updateVariant(Request $request, FabricVariant $fabricVariant): RedirectResponse
    {
        $this->authorize('update', $fabricVariant);

        $validated = $request->validate([
            'colour_name' => ['sometimes', 'string', 'max:80'],
            'colour_hex' => ['nullable', 'string', 'size:7'],
            'pattern' => ['nullable', 'string', 'max:80'],
            'price_per_yard_naira' => ['sometimes', 'integer', 'between:1,100000000'],
            'stock_yards' => ['sometimes', 'numeric', 'between:0,999999'],
            'low_stock_threshold_yards' => ['sometimes', 'numeric', 'between:0,9999'],
            'min_order_yards' => ['sometimes', 'numeric', 'between:0.25,50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['price_per_yard_naira'])) {
            $validated['price_per_yard_kobo'] = $validated['price_per_yard_naira'] * 100;
            unset($validated['price_per_yard_naira']);
        }

        $fabricVariant->update($validated);

        return back()->with('success', 'Variant updated.');
    }
}
