<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    | Customer uploads — measurements and photographs — are personal data under
    | the NDPA and are stored privately, served through signed or authorised
    | URLs. Catalogue imagery is public, because it is meant to be.
    */

    'disks' => [
        'public' => env('MEDIA_PUBLIC_DISK', 'public'),
        'private' => env('MEDIA_PRIVATE_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Derivatives
    |--------------------------------------------------------------------------
    | Generated on a queue after upload. A large share of Nigerian traffic is a
    | mid-range Android on 3G, so the smallest widths matter more than the
    | largest ones.
    */

    'widths' => [320, 640, 960, 1440, 1920],

    'formats' => ['avif', 'webp', 'jpg'],

    'quality' => [
        'avif' => 55,
        'webp' => 72,
        'jpg' => 80,
    ],

    'limits' => [
        'image_bytes' => 25 * 1024 * 1024,   // 25 MB
        'video_bytes' => 500 * 1024 * 1024,  // 500 MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Placeholders
    |--------------------------------------------------------------------------
    | Until the photography shoot happens, every catalogue image falls back to a
    | generated placeholder derived from the record itself, so the storefront
    | reads as finished rather than broken. Uploading a real image replaces it
    | with no other change. Set `show_badge` to false before going live if any
    | placeholders are still in place.
    */

    'placeholders' => [
        'enabled' => env('MEDIA_PLACEHOLDERS_ENABLED', true),
        'show_badge' => env('MEDIA_PLACEHOLDER_BADGE', true),
    ],
];
