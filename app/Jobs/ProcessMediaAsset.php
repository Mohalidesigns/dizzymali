<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Media\ImageSupport;
use App\Models\MediaAsset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Turns an uploaded original into the derivatives the storefront actually
 * serves.
 *
 * Three things happen here, and two of them are security rather than
 * performance:
 *
 *   1. The image is re-encoded through GD. That is what kills a polyglot
 *      payload — a file that is a valid JPEG and a valid PHP script at once
 *      does not survive being decoded to a pixel buffer and written out again.
 *   2. EXIF is discarded, because it carries GPS. A customer photographing a
 *      garment at home should not be publishing their address with it.
 *   3. AVIF, WebP and JPEG at five widths, so a mid-range Android on 3G fetches
 *      a 320px file rather than a 4000px one.
 */
class ProcessMediaAsset implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $mediaAssetId)
    {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $asset = MediaAsset::find($this->mediaAssetId);

        if ($asset === null || $asset->kind !== 'image') {
            return;
        }

        $asset->forceFill(['processing_status' => 'processing'])->save();

        try {
            $this->process($asset);
        } catch (\Throwable $e) {
            Log::error('Media processing failed.', [
                'media_asset_id' => $asset->id,
                'message' => $e->getMessage(),
            ]);

            $asset->forceFill([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    private function process(MediaAsset $asset): void
    {
        $disk = Storage::disk($asset->disk);
        $original = $disk->get($asset->path);

        if ($original === null) {
            throw new \RuntimeException("The original file for media asset {$asset->id} is missing.");
        }

        $image = @imagecreatefromstring($original);

        if ($image === false) {
            throw new \RuntimeException(ImageSupport::canDecode($asset->mime_type)
                ? 'That file could not be decoded as an image.'
                : ImageSupport::rejectionMessage($asset->mime_type));
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        /** @var list<int> $widths */
        $widths = (array) config('media.widths', [320, 640, 960, 1440, 1920]);
        /** @var list<string> $formats */
        $formats = (array) config('media.formats', ['webp', 'jpg']);

        $derivatives = [];
        $directory = trim(dirname($asset->path), '.');
        $base = pathinfo($asset->path, PATHINFO_FILENAME);

        foreach ($formats as $format) {
            if (! $this->formatSupported($format)) {
                continue;
            }

            foreach ($widths as $width) {
                // Never upscale. A 400px original becomes one 400px derivative,
                // not five increasingly blurry ones.
                if ($width > $sourceWidth) {
                    continue;
                }

                $height = (int) max(1, round($sourceHeight * ($width / $sourceWidth)));
                $resized = imagescale($image, $width, $height);

                if ($resized === false) {
                    continue;
                }

                $path = trim($directory.'/'.$base.'-'.$width.'.'.$format, '/');
                $disk->put($path, $this->encode($resized, $format));
                imagedestroy($resized);

                $derivatives[$format][$width] = $path;
            }
        }

        $asset->forceFill([
            'width' => $sourceWidth,
            'height' => $sourceHeight,
            'derivatives' => $derivatives,
            'dominant_hex' => $this->dominantColour($image),
            'processing_status' => 'ready',
            'processing_error' => null,
        ])->save();

        imagedestroy($image);
    }

    private function encode(\GdImage $image, string $format): string
    {
        /** @var array<string,int> $quality */
        $quality = (array) config('media.quality', []);

        ob_start();

        match ($format) {
            'avif' => imageavif($image, null, $quality['avif'] ?? 55),
            'webp' => imagewebp($image, null, $quality['webp'] ?? 72),
            default => imagejpeg($image, null, $quality['jpg'] ?? 80),
        };

        return (string) ob_get_clean();
    }

    private function formatSupported(string $format): bool
    {
        return match ($format) {
            'avif' => function_exists('imageavif'),
            'webp' => function_exists('imagewebp'),
            'jpg', 'jpeg' => function_exists('imagejpeg'),
            default => false,
        };
    }

    /** A single representative colour, for the loading state behind the image. */
    private function dominantColour(\GdImage $image): ?string
    {
        $sample = imagescale($image, 1, 1);

        if ($sample === false) {
            return null;
        }

        $rgb = imagecolorat($sample, 0, 0);
        imagedestroy($sample);

        return sprintf('#%02X%02X%02X', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }
}
