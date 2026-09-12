<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\GarmentTypeResource;
use App\Models\CmsBlock;
use App\Models\FabricVariant;
use App\Models\GarmentType;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('storefront/Home', [
            'blocks' => CmsBlock::published()->where('placement', 'homepage')->with('media')->get(),
            'garmentTypes' => GarmentTypeResource::collection(
                GarmentType::active()->orderBy('sort_order')->get(),
            ),
            'featuredFabrics' => FabricVariant::active()
                ->with('fabric.material')
                ->inRandomOrder()
                ->limit(6)
                ->get()
                ->map(fn (FabricVariant $v) => [
                    'id' => $v->id,
                    'name' => $v->displayName(),
                    'colour_hex' => $v->colour_hex,
                    'price_per_yard_kobo' => (int) $v->price_per_yard_kobo,
                ]),
        ]);
    }
}
