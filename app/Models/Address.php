<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $label
 * @property string $recipient_name
 * @property string $line_1
 * @property string|null $line_2
 * @property string $city
 * @property string|null $state_region
 * @property string|null $postcode
 * @property string $country_code
 * @property bool $is_default
 */
class Address extends Model
{
    /** @use HasFactory<\Database\Factories\AddressFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return $this->only([
            'recipient_name', 'phone', 'line_1', 'line_2',
            'city', 'state_region', 'postcode', 'country_code',
        ]);
    }

    public function singleLine(): string
    {
        return implode(', ', array_filter([
            $this->line_1, $this->line_2, $this->city,
            $this->state_region, $this->postcode, $this->country_code,
        ]));
    }
}
