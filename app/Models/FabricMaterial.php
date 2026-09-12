<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FabricMaterial extends Model
{
    /** @use HasFactory<\Database\Factories\FabricMaterialFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return HasMany<Fabric, $this> */
    public function fabrics(): HasMany
    {
        return $this->hasMany(Fabric::class);
    }
}
