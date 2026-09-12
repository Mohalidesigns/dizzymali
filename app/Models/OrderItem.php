<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pricing\FabricVariantSpec;
use App\Domain\Pricing\GarmentTypeSpec;
use App\Domain\Pricing\LineItemInput;
use App\Domain\Pricing\SelectedOption;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order_id
 * @property int $garment_type_id
 * @property int|null $fabric_variant_id
 * @property int $quantity
 * @property string $yards_required
 * @property string $yards_base
 * @property string $yards_size_adjustment
 * @property string $yards_customer_extra
 * @property int|null $measurement_profile_id
 * @property array<string,mixed>|null $measurement_snapshot
 * @property array<string,mixed>|null $garment_type_snapshot
 * @property array<string,mixed>|null $fabric_variant_snapshot
 * @property int $fabric_cost_kobo
 * @property int $sewing_cost_kobo
 * @property int $options_cost_kobo
 * @property int $line_total_kobo
 * @property string|null $style_notes
 * @property int|null $tailor_id
 * @property string $status
 * @property-read Order $order
 * @property-read GarmentType|null $garmentType
 * @property-read FabricVariant|null $fabricVariant
 * @property-read \Illuminate\Database\Eloquent\Collection<int,OrderItemOption> $options
 * @property-read \Illuminate\Database\Eloquent\Collection<int,OrderItemInspiration> $inspirations
 */
class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'yards_required' => 'decimal:2',
            'yards_base' => 'decimal:2',
            'yards_size_adjustment' => 'decimal:2',
            'yards_customer_extra' => 'decimal:2',
            'measurement_snapshot' => 'array',
            'garment_type_snapshot' => 'array',
            'fabric_variant_snapshot' => 'array',
            'fabric_cost_kobo' => 'integer',
            'sewing_cost_kobo' => 'integer',
            'options_cost_kobo' => 'integer',
            'line_total_kobo' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<GarmentType, $this> */
    public function garmentType(): BelongsTo
    {
        return $this->belongsTo(GarmentType::class);
    }

    /** @return BelongsTo<FabricVariant, $this> */
    public function fabricVariant(): BelongsTo
    {
        return $this->belongsTo(FabricVariant::class);
    }

    /** @return BelongsTo<MeasurementProfile, $this> */
    public function measurementProfile(): BelongsTo
    {
        return $this->belongsTo(MeasurementProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function tailor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tailor_id');
    }

    /** @return HasMany<OrderItemOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }

    /** @return HasMany<OrderItemInspiration, $this> */
    public function inspirations(): HasMany
    {
        return $this->hasMany(OrderItemInspiration::class)->orderBy('sort_order');
    }

    public function lineTotal(): Money
    {
        return Money::ofMinor((int) $this->line_total_kobo);
    }

    /**
     * Rebuild the pricing input for this line.
     *
     * On a live draft it reads the current catalogue. On a submitted order it
     * reads the snapshots, so a recalculation never silently reprices a garment
     * that is already being cut.
     */
    public function toPricingInput(bool $useSnapshots = false): LineItemInput
    {
        $garmentSpec = $useSnapshots && is_array($this->garment_type_snapshot)
            ? $this->garmentSpecFromSnapshot()
            : $this->garmentType->toSpec();

        $fabricSpec = $useSnapshots && is_array($this->fabric_variant_snapshot)
            ? $this->fabricSpecFromSnapshot()
            : $this->fabricVariant?->toSpec();

        return new LineItemInput(
            garmentType: $garmentSpec,
            fabricVariant: $fabricSpec,
            quantity: (int) $this->quantity,
            measurementsHundredths: $this->measurementsHundredths(),
            options: $this->options
                ->map(fn (OrderItemOption $o) => new SelectedOption(
                    id: $o->garment_option_id,
                    groupName: (string) $o->group_name,
                    optionName: (string) $o->option_name,
                    surchargeKobo: (int) $o->surcharge_kobo,
                    additionalYardsHundredths: (int) round(((float) $o->additional_yards) * 100),
                ))
                ->values()
                ->all(),
            customerExtraYardsHundredths: (int) round(((float) $this->yards_customer_extra) * 100),
        );
    }

    /** @return array<string,int> */
    public function measurementsHundredths(): array
    {
        $snapshot = $this->measurement_snapshot;

        if (! is_array($snapshot) || ! isset($snapshot['values'])) {
            return [];
        }

        $out = [];

        foreach ($snapshot['values'] as $value) {
            if (isset($value['key'], $value['value_inches'])) {
                $out[$value['key']] = (int) round(((float) $value['value_inches']) * 100);
            }
        }

        return $out;
    }

    private function garmentSpecFromSnapshot(): GarmentTypeSpec
    {
        $s = $this->garment_type_snapshot;

        return new GarmentTypeSpec(
            id: (int) ($s['id'] ?? 0),
            name: (string) ($s['name'] ?? 'Garment'),
            baseSewingCostKobo: (int) ($s['base_sewing_cost_kobo'] ?? 0),
            defaultYardageHundredths: (int) ($s['default_yardage_hundredths'] ?? 0),
            leadTimeDays: (int) ($s['lead_time_days'] ?? 21),
            baseWeightGrams: (int) ($s['base_weight_grams'] ?? 600),
            gramsPerYard: (int) ($s['grams_per_yard'] ?? 220),
            yardageRules: [],
        );
    }

    private function fabricSpecFromSnapshot(): ?FabricVariantSpec
    {
        $s = $this->fabric_variant_snapshot;

        if (! is_array($s) || ! isset($s['id'])) {
            return null;
        }

        return new FabricVariantSpec(
            id: (int) $s['id'],
            sku: (string) ($s['sku'] ?? ''),
            name: (string) ($s['name'] ?? ''),
            pricePerYardKobo: (int) ($s['price_per_yard_kobo'] ?? 0),
        );
    }
}
