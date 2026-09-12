<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasMediaAssets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $type
 * @property string $placement
 * @property string|null $title
 * @property array<string,mixed>|null $payload
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property bool $is_active
 * @property-read \Illuminate\Database\Eloquent\Collection<int,MediaAsset> $mediaAssets
 */
class CmsBlock extends Model
{
    use HasMediaAssets;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<CmsMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(CmsMedia::class)->orderBy('sort_order');
    }

    /** Live right now: switched on, and inside its scheduled window. */
    public function isLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<CmsBlock>  $query */
    public function scopePublished($query): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order');
    }
}
