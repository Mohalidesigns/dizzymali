<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\ProgressPhotoAdded;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProgressPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photographs from the workshop floor.
 *
 * A picture of a customer's own cloth on the cutting table is the single most
 * effective thing we can send someone who has paid four hundred thousand naira
 * to a workshop they have never visited.
 */
class ProgressPhotoController extends Controller
{
    public function store(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('manage', $order);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['file', 'image', 'mimetypes:image/jpeg,image/png,image/webp,image/heic', 'max:25600'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:255'],
            'alt_text' => ['required', 'string', 'max:255'],
            'notify' => ['sometimes', 'boolean'],
        ]);

        $disk = (string) config('media.disks.private', 'local');
        $notify = (bool) ($validated['notify'] ?? true);
        $first = null;

        foreach ($request->file('photos') as $file) {
            $photo = OrderProgressPhoto::create([
                'order_id' => $order->id,
                'uploaded_by' => $request->user()->id,
                'stage' => $order->status->value,
                'disk' => $disk,
                'path' => $file->store("orders/{$order->id}/progress", $disk),
                'caption' => $validated['caption'] ?? null,
                'alt_text' => $validated['alt_text'],
                'is_customer_visible' => true,
                'notified' => $notify,
            ]);

            $first ??= $photo;
        }

        // One notification per upload batch, not one per photo. Five photos of
        // the same garment is one piece of news.
        if ($notify && $first !== null) {
            ProgressPhotoAdded::dispatch($order, $first);
        }

        return back()->with('success', 'Photos uploaded'.($notify ? ' and the customer has been told.' : '.'));
    }

    /**
     * Streamed through an authorisation check rather than served from a public
     * bucket — these are photographs of one customer's order.
     */
    public function show(OrderProgressPhoto $progressPhoto): StreamedResponse
    {
        $this->authorize('view', $progressPhoto->order);

        abort_unless($progressPhoto->is_customer_visible, 404);

        return Storage::disk($progressPhoto->disk)->response($progressPhoto->path);
    }

    public function destroy(OrderProgressPhoto $progressPhoto): RedirectResponse
    {
        $this->authorize('manage', $progressPhoto->order);

        Storage::disk($progressPhoto->disk)->delete($progressPhoto->path);
        $progressPhoto->delete();

        return back();
    }
}
