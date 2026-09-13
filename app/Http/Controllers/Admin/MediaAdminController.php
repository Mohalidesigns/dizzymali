<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Media\StoreMediaAsset;
use App\Domain\Media\ImageSupport;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessMediaAsset;
use App\Models\CmsBlock;
use App\Models\Fabric;
use App\Models\FabricVariant;
use App\Models\GarmentType;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uploading the photography, when it arrives.
 *
 * Until then every catalogue record shows a generated placeholder, and this
 * screen is the list of what is still missing.
 *
 * One thing this screen has to be honest about: an upload is not finished when
 * the file lands. Derivatives are generated on the `media` queue, and only a
 * `ready` asset is served, so between the upload and the worker running the
 * tile legitimately still shows a placeholder. It must say so — silence there
 * is indistinguishable from the upload having failed.
 */
class MediaAdminController extends Controller
{
    /** Only these models may have media attached — not an arbitrary class name from a form. */
    private const ATTACHABLE = [
        'garment-type' => GarmentType::class,
        'fabric' => Fabric::class,
        'fabric-variant' => FabricVariant::class,
        'cms-block' => CmsBlock::class,
    ];

    public function __construct(private readonly StoreMediaAsset $store) {}

    public function index(): Response
    {
        $this->authorize('viewAny', MediaAsset::class);

        return Inertia::render('admin/Media', [
            // The file picker only offers what this server's GD can decode.
            'accepts' => implode(',', ImageSupport::decodableMimes()),

            'garmentTypes' => GarmentType::with('mediaAssets')->orderBy('sort_order')->get()
                ->map(fn (GarmentType $g) => $this->row('garment-type', $g, $g->name, 'hero')),

            'fabricVariants' => FabricVariant::with(['mediaAssets', 'fabric'])->get()
                ->map(fn (FabricVariant $v) => $this->row('fabric-variant', $v, $v->displayName(), 'swatch')),

            'summary' => [
                'awaiting_photography' => $this->awaitingCount(),
                'processing' => MediaAsset::whereIn('processing_status', ['pending', 'processing'])->count(),
                'failed' => MediaAsset::where('processing_status', 'failed')->count(),

                // An upload sitting in `pending` for minutes means nothing is
                // working the `media` queue. That is the most common way this
                // screen appears broken while every line of it is behaving.
                'stalled' => MediaAsset::whereIn('processing_status', ['pending', 'processing'])
                    ->where('created_at', '<', now()->subMinutes(2))
                    ->count(),

                // And the second most common: derivatives generated correctly,
                // then served from a symlink that was never created.
                'storage_linked' => $this->storageLinked(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MediaAsset::class);

        $validated = $request->validate([
            'attachable_type' => ['required', Rule::in(array_keys(self::ATTACHABLE))],
            'attachable_id' => ['required', 'integer'],
            'collection' => ['required', 'string', 'max:40'],
            // Required before publish, per the plan's hard rules. An image
            // nobody can describe is an image half our customers cannot use.
            'alt_text' => ['required', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
            'file' => ['required', 'file', 'max:512000'],
        ]);

        $model = $this->resolve($validated['attachable_type'], (int) $validated['attachable_id']);

        try {
            $this->store->handle(
                attachable: $model,
                file: $request->file('file'),
                altText: $validated['alt_text'],
                collection: $validated['collection'],
                caption: $validated['caption'] ?? null,
                isPrimary: (bool) ($validated['is_primary'] ?? true),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('success', 'Uploaded. Derivatives are being generated on the media queue.');
    }

    /**
     * The uploaded original, for staff only, streamed off the disk.
     *
     * Not a public-disk URL: an original that has not been through the pipeline
     * still carries its EXIF, and that includes where the photograph was taken.
     */
    public function preview(MediaAsset $mediaAsset): StreamedResponse
    {
        $this->authorize('view', $mediaAsset);

        $disk = Storage::disk($mediaAsset->disk);

        abort_unless($disk->exists($mediaAsset->path), 404);

        return $disk->response($mediaAsset->path, null, [
            'Content-Type' => $mediaAsset->mime_type,
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    /** Put a failed or stuck asset back on the queue without re-uploading it. */
    public function retry(MediaAsset $mediaAsset): RedirectResponse
    {
        $this->authorize('update', $mediaAsset);

        if ($mediaAsset->kind !== 'image') {
            return back()->withErrors(['file' => 'Only images go through the pipeline.']);
        }

        $mediaAsset->forceFill([
            'processing_status' => 'pending',
            'processing_error' => null,
        ])->save();

        ProcessMediaAsset::dispatch($mediaAsset->id);

        return back()->with('success', 'Queued again. A worker has to be running on the media queue to finish it.');
    }

    public function destroy(MediaAsset $mediaAsset): RedirectResponse
    {
        $this->authorize('delete', $mediaAsset);

        $disk = Storage::disk($mediaAsset->disk);
        $disk->delete($mediaAsset->path);

        foreach ((array) $mediaAsset->derivatives as $paths) {
            foreach ((array) $paths as $path) {
                $disk->delete((string) $path);
            }
        }

        $mediaAsset->delete();

        return back()->with('success', 'Image removed. The placeholder is back in its place.');
    }

    private function resolve(string $type, int $id): Model
    {
        /** @var class-string<Model> $class */
        $class = self::ATTACHABLE[$type];

        return $class::query()->findOrFail($id);
    }

    /** @return array<string,mixed> */
    private function row(string $type, Model $model, string $label, string $collection): array
    {
        // The latest asset in this collection whatever state it is in.
        //
        // This used to read the status off primaryMedia(), which filters to
        // `ready` — so a pending or failed upload reported no status at all and
        // the tile sat showing the placeholder with nothing to explain why.
        /** @phpstan-ignore-next-line property.notFound */
        $assets = $model->mediaAssets;

        /** @var MediaAsset|null $latest */
        $latest = $assets->where('collection', $collection)->sortByDesc('id')->first();

        return [
            'type' => $type,
            'id' => $model->getKey(),
            'label' => $label,
            'collection' => $collection,
            /** @phpstan-ignore-next-line method.notFound */
            'image' => $model->imageFor($collection, 400, 500),
            'asset_id' => $latest?->id,
            'processing_status' => $latest?->processing_status,
            'processing_error' => $latest?->processing_error,
            // What they just uploaded, so they can see it arrived even while
            // the derivatives are still to be generated.
            'preview_url' => $latest !== null && ! $latest->isReady()
                ? route('admin.media.preview', $latest)
                : null,
        ];
    }

    /** The local `public` disk serves through a symlink that has to exist. S3 does not. */
    private function storageLinked(): bool
    {
        if ((string) config('media.disks.public', 'public') !== 'public') {
            return true;
        }

        return file_exists(public_path('storage'));
    }

    private function awaitingCount(): int
    {
        $garments = GarmentType::with('mediaAssets')->get()
            ->filter(fn (GarmentType $g) => $g->primaryMedia('hero') === null)
            ->count();

        $variants = FabricVariant::with('mediaAssets')->get()
            ->filter(fn (FabricVariant $v) => $v->primaryMedia('swatch') === null)
            ->count();

        return $garments + $variants;
    }
}
