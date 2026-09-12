<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Media\Placeholder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string $collection
 * @property string $disk
 * @property string $path
 * @property string $kind
 * @property string $alt_text
 * @property string|null $caption
 * @property array<string,mixed>|null $derivatives
 * @property string|null $blurhash
 * @property string|null $dominant_hex
 * @property string $processing_status
 * @property int $sort_order
 * @property bool $is_primary
 */
class MediaAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'derivatives' => 'array',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isReady(): bool
    {
        return $this->processing_status === 'ready';
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * A srcset across the generated widths, newest format first.
     *
     * Returns an empty string until the queued job has run, so the caller falls
     * back to the original rather than pointing at files that do not exist yet.
     */
    public function srcset(string $format = 'webp'): string
    {
        $set = $this->derivatives[$format] ?? null;

        if (! is_array($set) || $set === []) {
            return '';
        }

        $disk = Storage::disk($this->disk);
        $parts = [];

        foreach ($set as $width => $path) {
            $parts[] = $disk->url((string) $path).' '.(int) $width.'w';
        }

        return implode(', ', $parts);
    }

    /**
     * Everything a responsive <img> needs, whether or not a real image exists.
     *
     * @return array{src:string,srcset:string,alt:string,blurhash:string|null,is_placeholder:bool}
     */
    public static function present(
        ?self $asset,
        string $seed,
        string $label,
        int $width = 800,
        int $height = 1000,
        ?string $hex = null,
    ): array {
        if ($asset !== null) {
            return [
                'src' => $asset->url(),
                'srcset' => $asset->srcset(),
                'alt' => $asset->alt_text,
                'blurhash' => $asset->blurhash,
                'is_placeholder' => false,
            ];
        }

        return [
            'src' => Placeholder::dataUri(
                $seed,
                $label,
                $width,
                $height,
                $hex,
                (bool) config('media.placeholders.show_badge', true),
            ),
            'srcset' => '',
            // Honest alt text. Pretending a placeholder is the garment would be
            // worse for a screen-reader user than admitting the photo is missing.
            'alt' => $label.' — photograph to come',
            'blurhash' => null,
            'is_placeholder' => true,
        ];
    }
}
