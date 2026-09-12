<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CmsAdminController extends Controller
{
    private const TYPES = [
        'hero', 'slider', 'carousel', 'video_feature',
        'lookbook_grid', 'testimonial', 'promo_banner',
    ];

    private const PLACEMENTS = ['homepage', 'garment_page', 'lookbook'];

    public function index(): Response
    {
        $this->authorize('viewAny', CmsBlock::class);

        return Inertia::render('admin/Cms', [
            'blocks' => CmsBlock::with('mediaAssets')->orderBy('placement')->orderBy('sort_order')->get()
                ->map(fn (CmsBlock $b) => [
                    'id' => $b->id,
                    'type' => $b->type,
                    'placement' => $b->placement,
                    'title' => $b->title,
                    'payload' => $b->payload,
                    'sort_order' => $b->sort_order,
                    'is_active' => $b->is_active,
                    'starts_at' => $b->starts_at?->toDateTimeString(),
                    'ends_at' => $b->ends_at?->toDateTimeString(),
                    'is_live' => $b->isLive(),
                    'media_count' => $b->mediaAssets->count(),
                ]),
            'types' => self::TYPES,
            'placements' => self::PLACEMENTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CmsBlock::class);

        CmsBlock::create($this->validated($request) + ['is_active' => false]);

        return back()->with('success', 'Block created. It stays unpublished until you switch it on.');
    }

    public function update(Request $request, CmsBlock $cmsBlock): RedirectResponse
    {
        $this->authorize('update', $cmsBlock);

        $data = $this->validated($request, partial: true);

        // Nothing goes live with an image that has no alt text. This is the one
        // publish rule that is not negotiable.
        if (($data['is_active'] ?? false) && $this->hasMediaMissingAltText($cmsBlock)) {
            return back()->withErrors([
                'is_active' => 'Every image in this block needs alt text before it can be published.',
            ]);
        }

        $cmsBlock->update($data);

        return back()->with('success', 'Block updated.');
    }

    public function destroy(CmsBlock $cmsBlock): RedirectResponse
    {
        $this->authorize('delete', $cmsBlock);

        $cmsBlock->delete();

        return back()->with('success', 'Block removed.');
    }

    /** Drag-to-reorder sends the whole ordered list back. */
    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('create', CmsBlock::class);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:cms_blocks,id'],
        ]);

        foreach ($validated['ids'] as $position => $id) {
            CmsBlock::whereKey($id)->update(['sort_order' => $position]);
        }

        return back();
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'type' => [$required, Rule::in(self::TYPES)],
            'placement' => [$required, Rule::in(self::PLACEMENTS)],
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'payload' => ['sometimes', 'nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'between:0,9999'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function hasMediaMissingAltText(CmsBlock $block): bool
    {
        return $block->mediaAssets()->where(fn ($q) => $q->whereNull('alt_text')->orWhere('alt_text', ''))->exists();
    }
}
