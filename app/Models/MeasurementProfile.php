<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A customer's saved set of body measurements.
 *
 * This is personal data under the NDPA 2023. Read the policy before you touch
 * anything here: an IDOR on this model is a reportable breach, not a bug.
 */
/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $unit_preference
 * @property string $source
 * @property string $review_status
 * @property string|null $notes
 * @property string|null $review_notes
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int,MeasurementValue> $values
 */
class MeasurementProfile extends Model
{
    /** @use HasFactory<\Database\Factories\MeasurementProfileFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<MeasurementValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(MeasurementValue::class);
    }

    public function isPendingReview(): bool
    {
        return $this->review_status === 'pending_review';
    }

    /** @return array<string,float> field key => inches */
    public function toInchesMap(): array
    {
        $values = $this->relationLoaded('values')
            ? $this->values
            : $this->values()->with('measurementField')->get();

        $out = [];

        foreach ($values as $value) {
            if ($value->measurementField !== null) {
                $out[$value->measurementField->key] = (float) $value->value_inches;
            }
        }

        return $out;
    }

    /** @return array<string,int> field key => inches × 100, for the pricing engine */
    public function toHundredthsMap(): array
    {
        return array_map(
            static fn (float $inches): int => (int) round($inches * 100),
            $this->toInchesMap(),
        );
    }

    /**
     * The frozen copy written onto an order item. Never a foreign key — if the
     * customer edits this profile next year, the garment already cut must not
     * change.
     *
     * @return array<string,mixed>
     */
    public function snapshot(): array
    {
        $values = $this->relationLoaded('values')
            ? $this->values
            : $this->values()->with('measurementField')->get();

        return [
            'profile_id' => $this->id,
            'name' => $this->name,
            'unit_preference' => $this->unit_preference,
            'source' => $this->source,
            'captured_at' => now()->toIso8601String(),
            'values' => $values
                ->filter(fn (MeasurementValue $v) => $v->measurementField !== null)
                ->map(fn (MeasurementValue $v) => [
                    'key' => $v->measurementField->key,
                    'label' => $v->measurementField->label,
                    'group' => $v->measurementField->group->value,
                    'value_inches' => (float) $v->value_inches,
                ])
                ->values()
                ->all(),
        ];
    }
}
