<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GarmentOptionGroup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'allows_multiple' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<GarmentOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(GarmentOption::class)->orderBy('sort_order');
    }
}
