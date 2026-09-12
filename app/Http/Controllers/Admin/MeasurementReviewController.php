<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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

/** The queue of uploaded measurement sheets awaiting transcription by staff. */
class MeasurementReviewController extends Controller
{
    public function __construct(private readonly SaveMeasurementProfile $save) {}

    public function index(): Response
    {
        $this->authorize('reviewAny', MeasurementProfile::class);

        return Inertia::render('admin/MeasurementReviews', [
            'profiles' => MeasurementProfileResource::collection(
                MeasurementProfile::query()
                    ->where('review_status', 'pending_review')
                    ->with(['values.measurementField', 'user:id,name,email'])
                    ->oldest()
                    ->get(),
            ),
            'fields' => MeasurementFieldResource::collection(
                MeasurementField::where('is_active', true)->orderBy('sort_order')->get(),
            ),
        ]);
    }

    public function approve(SaveMeasurementProfileRequest $request, MeasurementProfile $measurementProfile): RedirectResponse
    {
        $this->authorize('review', $measurementProfile);

        $this->save->handle($measurementProfile->user, $request->validated(), $measurementProfile);

        $measurementProfile->forceFill([
            'review_status' => 'ready',
            'reviewed_by' => $request->user()->id,
            'verified_at' => now(),
        ])->save();

        return back()->with('success', 'Measurements transcribed and verified.');
    }

    public function reject(Request $request, MeasurementProfile $measurementProfile): RedirectResponse
    {
        $this->authorize('review', $measurementProfile);

        $validated = $request->validate(['review_notes' => ['required', 'string', 'max:1000']]);

        $measurementProfile->forceFill([
            'review_status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'review_notes' => $validated['review_notes'],
        ])->save();

        return back()->with('success', 'Sent back to the customer.');
    }
}
