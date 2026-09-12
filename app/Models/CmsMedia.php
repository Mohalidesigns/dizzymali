<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsMedia extends Model
{
    protected $table = 'cms_media';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['derivatives' => 'array', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<CmsBlock, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(CmsBlock::class, 'cms_block_id');
    }
}
