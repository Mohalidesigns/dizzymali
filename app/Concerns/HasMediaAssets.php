<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Attach images to a model, with a placeholder standing in until a real one is
 * uploaded.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HasMediaAssets
{
    /** @return MorphMany<MediaAsset, $this> */
    public function mediaAssets(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'attachable')->orderBy('sort_order');
    }

    public function primaryMedia(string $collection = 'default'): ?MediaAsset
    {
        $assets = $this->relationLoaded('mediaAssets')
            ? $this->mediaAssets
            : $this->mediaAssets()->get();

        return $assets
            ->where('collection', $collection)
            ->where('processing_status', 'ready')
            ->sortByDesc('is_primary')
            ->first();
    }

    /**
     * The image payload for this model, real or placeholder.
     *
     * @return array{src:string,srcset:string,alt:string,blurhash:string|null,is_placeholder:bool}
     */
    public function imageFor(
        string $collection = 'default',
        int $width = 800,
        int $height = 1000,
        ?string $hex = null,
    ): array {
        return MediaAsset::present(
            $this->primaryMedia($collection),
            $this->placeholderSeed(),
            $this->placeholderLabel(),
            $width,
            $height,
            $hex ?? $this->placeholderHex(),
        );
    }

    /**
     * Stable identity so the same record always gets the same placeholder.
     *
     * Reads the raw attribute array rather than calling getAttribute, because
     * strict mode throws on a column the model does not have — and not every
     * model here has a slug.
     */
    public function placeholderSeed(): string
    {
        return static::class.':'.($this->rawAttribute('slug') ?? $this->rawAttribute('sku') ?? $this->getKey());
    }

    public function placeholderLabel(): string
    {
        return (string) ($this->rawAttribute('name') ?? 'DizzyMali');
    }

    public function placeholderHex(): ?string
    {
        $hex = $this->rawAttribute('colour_hex');

        return is_string($hex) ? $hex : null;
    }

    private function rawAttribute(string $key): mixed
    {
        return $this->getAttributes()[$key] ?? null;
    }
}
