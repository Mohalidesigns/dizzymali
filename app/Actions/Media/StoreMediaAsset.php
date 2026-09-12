<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Jobs\ProcessMediaAsset;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Takes an uploaded file and puts it into the pipeline.
 *
 * MIME type is read from the file's contents, never from its extension or from
 * the Content-Type header the browser supplied — both are attacker-controlled.
 */
class StoreMediaAsset
{
    public function handle(
        Model $attachable,
        UploadedFile $file,
        string $altText,
        string $collection = 'default',
        ?string $caption = null,
        bool $isPrimary = false,
        ?string $disk = null,
    ): MediaAsset {
        $mime = (string) $file->getMimeType();
        $kind = str_starts_with($mime, 'video/') ? 'video' : 'image';

        $this->assertAcceptable($mime, (int) $file->getSize(), $kind);

        $disk ??= (string) config('media.disks.public', 'public');
        $folder = $this->folderFor($attachable, $collection);
        $path = $file->store($folder, $disk);

        $asset = MediaAsset::create([
            'attachable_type' => $attachable::class,
            'attachable_id' => $attachable->getKey(),
            'collection' => $collection,
            'disk' => $disk,
            'path' => $path,
            'kind' => $kind,
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
            'alt_text' => $altText,
            'caption' => $caption,
            // Video is stored as uploaded; only images go through GD.
            'processing_status' => $kind === 'image' ? 'pending' : 'ready',
            'is_primary' => $isPrimary,
            'sort_order' => $this->nextSortOrder($attachable, $collection),
        ]);

        if ($isPrimary) {
            MediaAsset::query()
                ->where('attachable_type', $attachable::class)
                ->where('attachable_id', $attachable->getKey())
                ->where('collection', $collection)
                ->whereKeyNot($asset->id)
                ->update(['is_primary' => false]);
        }

        if ($kind === 'image') {
            ProcessMediaAsset::dispatch($asset->id);
        }

        return $asset;
    }

    private function assertAcceptable(string $mime, int $bytes, string $kind): void
    {
        $allowed = $kind === 'video'
            ? ['video/mp4', 'video/quicktime', 'video/webm']
            : ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic'];

        if (! in_array($mime, $allowed, true)) {
            throw new \InvalidArgumentException("Files of type {$mime} are not accepted here.");
        }

        $limit = $kind === 'video'
            ? (int) config('media.limits.video_bytes')
            : (int) config('media.limits.image_bytes');

        if ($bytes > $limit) {
            throw new \InvalidArgumentException(sprintf(
                'That file is %s MB. The limit is %s MB.',
                round($bytes / 1_048_576, 1),
                round($limit / 1_048_576),
            ));
        }
    }

    private function folderFor(Model $attachable, string $collection): string
    {
        $type = str_replace('\\', '-', strtolower(class_basename($attachable)));

        return "media/{$type}/{$attachable->getKey()}/{$collection}";
    }

    private function nextSortOrder(Model $attachable, string $collection): int
    {
        return (int) MediaAsset::query()
            ->where('attachable_type', $attachable::class)
            ->where('attachable_id', $attachable->getKey())
            ->where('collection', $collection)
            ->max('sort_order') + 1;
    }
}
