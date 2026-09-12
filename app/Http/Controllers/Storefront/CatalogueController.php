<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\FabricVariantResource;
use App\Http\Resources\GarmentTypeResource;
use App\Models\FabricMaterial;
use App\Models\FabricVariant;
use App\Models\GarmentType;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogueController extends Controller
{
    public function garments(): Response
    {
        return Inertia::render('storefront/Garments', [
            'seo' => Seo::make(
                title: 'Garments — Agbada, Kaftan, Jalabiya and Danshiki',
                description: 'Four cuts, each made to your measurements. Choose by the occasion rather than by size.',
            ),
            'garmentTypes' => GarmentTypeResource::collection(
                GarmentType::active()
                    ->with(['measurementFields', 'optionGroups.options'])
                    ->orderBy('sort_order')
                    ->get(),
            ),
        ]);
    }

    public function garment(GarmentType $garmentType): Response
    {
        abort_unless($garmentType->is_active, 404);

        $garmentType->load(['measurementFields', 'optionGroups.options', 'yardageRules.measurementField']);

        return Inertia::render('storefront/Garment', [
            'garmentType' => new GarmentTypeResource($garmentType),
            'seo' => Seo::make(
                title: $garmentType->meta_title ?? ($garmentType->name.' — made to measure'),
                description: $garmentType->meta_description
                    ?? (string) ($garmentType->description ?? $garmentType->tagline ?? ''),
                structuredData: Seo::garmentProduct(
                    name: (string) $garmentType->name,
                    description: (string) ($garmentType->description ?? ''),
                    url: route('garments.show', $garmentType->slug),
                    fromKobo: (int) $garmentType->base_sewing_cost_kobo,
                ),
            ),
        ]);
    }

    public function fabrics(Request $request): Response
    {
        $query = FabricVariant::active()
            ->with('fabric.material')
            ->whereHas('fabric', fn ($q) => $q->where('is_active', true));

        if ($material = $request->string('material')->toString()) {
            $query->whereHas('fabric.material', fn ($q) => $q->where('slug', $material));
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_yard_kobo', '<=', (int) $request->integer('max_price') * 100);
        }

        if ($request->boolean('in_stock')) {
            $query->whereColumn('stock_yards', '>', 'reserved_yards');
        }

        return Inertia::render('storefront/Fabrics', [
            'seo' => Seo::make(
                title: 'Fabrics — cashmere, linen, cotton and nylon by the yard',
                description: 'Browse the cloth we cut from, priced by the yard, with weight, origin and drape for every one.',
            ),
            'variants' => FabricVariantResource::collection(
                $query->orderBy('price_per_yard_kobo')->paginate(24)->withQueryString(),
            ),
            'materials' => FabricMaterial::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'slug', 'name', 'description']),
            'filters' => $request->only(['material', 'max_price', 'in_stock']),
        ]);
    }
}
