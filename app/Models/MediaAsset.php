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
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property string $alt_text
 * @property string|null $caption
 * @property array<string,mixed>|null $derivatives
 * @property string|null $blurhash
 * @property string|null $dominant_hex
 * @property string $processing_status
 * @property string|null $processing_error
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
     * The best derivative format this asset actually has.
     *
     * config/media.formats is a preference order, not a promise: the job skips
     * any format the server's GD cannot encode (a stock XAMPP build has no
     * WebP), so the presenter has to serve what exists rather than what was
     * hoped for. Null until the queued job has run.
     */
    public function availableFormat(): ?string
    {
        /** @var list<string> $preferred */
        $preferred = (array) config('media.formats', ['webp', 'jpg']);

        foreach ($preferred as $format) {
            $set = $this->derivatives[$format] ?? null;

            if (is_array($set) && $set !== []) {
                return $format;
            }
        }

        return null;
    }

    /**
     * A srcset across the generated widths in one format — a srcset cannot mix
     * formats, so the caller picks one, defaulting to the best available.
     *
     * Returns an empty string until the queued job has run, so the caller falls
     * back to the original rather than pointing at files that do not exist yet.
     */
    public function srcset(?string $format = null): string
    {
        $format ??= $this->availableFormat();
        $set = $format === null ? null : ($this->derivatives[$format] ?? null);

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
     * The URL a plain <img src> should carry: the widest JPEG derivative, which
     * every browser can decode and which has been resized and stripped of EXIF.
     * Only if no derivative exists at all does this fall back to the original.
     */
    public function fallbackUrl(): string
    {
        $jpg = $this->derivatives['jpg'] ?? null;

        if (! is_array($jpg) || $jpg === []) {
            $format = $this->availableFormat();
            $jpg = $format === null ? null : ($this->derivatives[$format] ?? null);
        }

        if (! is_array($jpg) || $jpg === []) {
            return $this->url();
        }

        $widest = max(array_map('intval', array_keys($jpg)));

        return Storage::disk($this->disk)->url((string) $jpg[$widest]);
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
                'src' => $asset->fallbackUrl(),
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
