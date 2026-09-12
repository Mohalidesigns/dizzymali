<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Measurements\SaveMeasurementProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMeasurementProfileRequest;
use App\Http\Resources\MeasurementFieldResource;
use App\Http\Resources\MeasurementProfileResource;
use App\Models\MeasurementField;
use App\Models\MeasurementProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeasurementProfileController extends Controller
{
    public function __construct(private readonly SaveMeasurementProfile $save) {}

    public function index(Request $request): Response
    {
        return Inertia::render('storefront/Measurements', [
            'profiles' => MeasurementProfileResource::collection(
                $request->user()->measurementProfiles()->with('values.measurementField')->latest()->get(),
            ),
            'fields' => MeasurementFieldResource::collection(
                MeasurementField::query()->where('is_active', true)->orderBy('sort_order')->get(),
            ),
            'unitPreference' => $request->user()->unit_preference ?? 'in',
        ]);
    }

    public function store(SaveMeasurementProfileRequest $request): RedirectResponse
    {
        $this->authorize('create', MeasurementProfile::class);

        $this->save->handle($request->user(), $request->validated());

        return back()->with('success', 'Measurements saved.');
    }

    public function update(SaveMeasurementProfileRequest $request, MeasurementProfile $measurementProfile): RedirectResponse
    {
        $this->authorize('update', $measurementProfile);

        $this->save->handle($request->user(), $request->validated(), $measurementProfile);

        return back()->with('success', 'Measurements updated.');
    }

    public function destroy(MeasurementProfile $measurementProfile): RedirectResponse
    {
        $this->authorize('delete', $measurementProfile);

        $measurementProfile->delete();

        return back()->with('success', 'Profile removed.');
    }
}
