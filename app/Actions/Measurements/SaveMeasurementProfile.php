<?php

declare(strict_types=1);

namespace App\Actions\Measurements;

use App\Models\MeasurementField;
use App\Models\MeasurementProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a customer's measurements.
 *
 * Values arrive in whatever unit the customer was typing in and are stored in
 * inches and nothing but inches. Out-of-range values are rejected here rather
 * than discovered after the fabric is cut.
 */
class SaveMeasurementProfile
{
    /**
     * @param  array{name?:string,unit?:string,source?:string,notes?:string,is_default?:bool,values?:array<string,float|int|string|null>}  $data
     */
    public function handle(User $user, array $data, ?MeasurementProfile $profile = null): MeasurementProfile
    {
        $unit = strtolower((string) ($data['unit'] ?? $user->unit_preference ?? 'in'));
        $fields = MeasurementField::query()->where('is_active', true)->get()->keyBy('key');

        $normalised = [];
        $errors = [];

        foreach ($data['values'] ?? [] as $key => $raw) {
            $field = $fields->get($key);

            if ($field === null || $raw === null || $raw === '') {
                continue;
            }

            $inches = $unit === 'cm' ? ((float) $raw) / 2.54 : (float) $raw;
            $inches = round($inches, 2);

            if (! $field->isPlausible($inches)) {
                $errors["values.{$key}"] = sprintf(
                    '%s should be between %s and %s inches. Please check that measurement.',
                    $field->label,
                    rtrim(rtrim((string) $field->min_inches, '0'), '.'),
                    rtrim(rtrim((string) $field->max_inches, '0'), '.'),
                );

                continue;
            }

            $normalised[(int) $field->id] = $inches;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($user, $data, $unit, $profile, $normalised) {
            $profile ??= new MeasurementProfile(['user_id' => $user->id]);

            $profile->fill([
                'user_id' => $user->id,
                'name' => $data['name'] ?? $profile->name ?? 'My measurements',
                'unit_preference' => $unit,
                'source' => $data['source'] ?? $profile->source ?? 'manual',
                'notes' => $data['notes'] ?? $profile->notes,
            ])->save();

            foreach ($normalised as $fieldId => $inches) {
                $profile->values()->updateOrCreate(
                    ['measurement_field_id' => $fieldId],
                    ['value_inches' => $inches],
                );
            }

            if (($data['is_default'] ?? false) || $user->measurementProfiles()->count() === 1) {
                $user->measurementProfiles()->whereKeyNot($profile->id)->update(['is_default' => false]);
                $profile->forceFill(['is_default' => true])->save();
            }

            return $profile->refresh()->load('values.measurementField');
        });
    }
}
