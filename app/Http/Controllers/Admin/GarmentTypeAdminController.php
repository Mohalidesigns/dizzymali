<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\GarmentTypeResource;
use App\Models\GarmentType;
use App\Models\MeasurementField;
use App\Models\YardageRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GarmentTypeAdminController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', GarmentType::class);

        return Inertia::render('admin/GarmentTypes', [
            'garmentTypes' => GarmentTypeResource::collection(
                GarmentType::with(['measurementFields', 'yardageRules.measurementField'])
                    ->orderBy('sort_order')
                    ->get(),
            ),
            'yardageRules' => YardageRule::with('measurementField:id,key,label')
                ->get()
                ->groupBy('garment_type_id'),
            'measurementFields' => MeasurementField::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, GarmentType $garmentType): RedirectResponse
    {
        $this->authorize('update', $garmentType);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:3000'],
            'base_sewing_cost_naira' => ['sometimes', 'integer', 'between:0,100000000'],
            'default_yardage' => ['sometimes', 'numeric', 'between:0.5,30'],
            'lead_time_days' => ['sometimes', 'integer', 'between:1,180'],
            'base_weight_grams' => ['sometimes', 'integer', 'between:50,10000'],
            'grams_per_yard' => ['sometimes', 'integer', 'between:20,2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['base_sewing_cost_naira'])) {
            $validated['base_sewing_cost_kobo'] = $validated['base_sewing_cost_naira'] * 100;
            unset($validated['base_sewing_cost_naira']);
        }

        $garmentType->update($validated);

        return back()->with('success', 'Garment updated.');
    }

    public function storeYardageRule(Request $request, GarmentType $garmentType): RedirectResponse
    {
        $this->authorize('update', $garmentType);

        $validated = $request->validate([
            'measurement_field_id' => ['required', 'exists:measurement_fields,id'],
            'threshold_inches' => ['required', 'numeric', 'between:1,120'],
            'additional_yards' => ['required', 'numeric', 'between:0.05,10'],
        ]);

        $garmentType->yardageRules()->create($validated + ['is_active' => true]);

        return back()->with('success', 'Yardage rule added.');
    }

    public function destroyYardageRule(GarmentType $garmentType, YardageRule $yardageRule): RedirectResponse
    {
        $this->authorize('update', $garmentType);

        abort_unless($yardageRule->garment_type_id === $garmentType->id, 404);

        $yardageRule->delete();

        return back();
    }
}
