<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Media\StoreMediaAsset;
use App\Http\Controllers\Controller;
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

/**
 * Uploading the photography, when it arrives.
 *
 * Until then every catalogue record shows a generated placeholder, and this
 * screen is the list of what is still missing.
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
            'garmentTypes' => GarmentType::with('mediaAssets')->orderBy('sort_order')->get()
                ->map(fn (GarmentType $g) => $this->row('garment-type', $g, $g->name, 'hero')),

            'fabricVariants' => FabricVariant::with(['mediaAssets', 'fabric'])->get()
                ->map(fn (FabricVariant $v) => $this->row('fabric-variant', $v, $v->displayName(), 'swatch')),

            'summary' => [
                'awaiting_photography' => $this->awaitingCount(),
                'processing' => MediaAsset::whereIn('processing_status', ['pending', 'processing'])->count(),
                'failed' => MediaAsset::where('processing_status', 'failed')->count(),
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

        return back()->with('success', 'Uploaded. Derivatives are being generated in the background.');
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
        // Every model listed in self::ATTACHABLE uses HasMediaAssets.
        /** @phpstan-ignore-next-line method.notFound */
        $asset = $model->primaryMedia($collection);

        return [
            'type' => $type,
            'id' => $model->getKey(),
            'label' => $label,
            'collection' => $collection,
            /** @phpstan-ignore-next-line method.notFound */
            'image' => $model->imageFor($collection, 400, 500),
            'asset_id' => $asset?->id,
            'processing_status' => $asset?->processing_status,
        ];
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
