<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Which image formats this server can actually read.
 *
 * The pipeline decodes with GD, and GD's format support is a build-time
 * decision — a stock XAMPP or Homebrew PHP often has no WebP or AVIF, and
 * nothing decodes HEIC. Accepting a format at upload that the job will then
 * choke on produces a "failed" tile with nothing the admin can do about it, so
 * the accepted list is derived from the runtime rather than written down.
 */
final class ImageSupport
{
    /** @var array<string,array{label:string,fn:string}> */
    private const CANDIDATES = [
        'image/jpeg' => ['label' => 'JPEG', 'fn' => 'imagecreatefromjpeg'],
        'image/png' => ['label' => 'PNG', 'fn' => 'imagecreatefrompng'],
        'image/gif' => ['label' => 'GIF', 'fn' => 'imagecreatefromgif'],
        'image/webp' => ['label' => 'WebP', 'fn' => 'imagecreatefromwebp'],
        'image/avif' => ['label' => 'AVIF', 'fn' => 'imagecreatefromavif'],
    ];

    /** @return list<string> MIME types the pipeline can decode here. */
    public static function decodableMimes(): array
    {
        $mimes = [];

        foreach (self::CANDIDATES as $mime => $candidate) {
            if (function_exists($candidate['fn'])) {
                $mimes[] = $mime;
            }
        }

        return $mimes;
    }

    public static function canDecode(string $mime): bool
    {
        return in_array($mime, self::decodableMimes(), true);
    }

    /** A human name for a MIME type, for error messages. */
    public static function label(string $mime): string
    {
        return match ($mime) {
            'image/heic', 'image/heif' => 'HEIC',
            default => self::CANDIDATES[$mime]['label'] ?? $mime,
        };
    }

    /** Why an upload of this type is being refused, and what to do instead. */
    public static function rejectionMessage(string $mime): string
    {
        $accepted = implode(', ', array_map(self::label(...), self::decodableMimes()));

        return sprintf(
            'This server cannot read %s images, so it could not process this file. Upload it as %s instead.',
            self::label($mime),
            $accepted,
        );
    }
}
