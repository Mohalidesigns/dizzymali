<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadInspirationRequest;
use App\Models\OrderItem;
use App\Models\OrderItemInspiration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InspirationController extends Controller
{
    public function store(UploadInspirationRequest $request, OrderItem $orderItem): RedirectResponse
    {
        $this->authorize('update', $orderItem);

        $disk = config('filesystems.default');

        foreach ($request->file('images', []) as $index => $file) {
            // Stored private. EXIF (which carries GPS) is stripped by the
            // re-encode job; nothing here is ever written to a public bucket.
            $path = $file->store("orders/{$orderItem->order_id}/inspirations", $disk);

            OrderItemInspiration::create([
                'order_item_id' => $orderItem->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'sort_order' => $orderItem->inspirations()->count() + $index,
            ]);
        }

        return back()->with('success', 'Reference images uploaded.');
    }

    public function show(OrderItemInspiration $inspiration): StreamedResponse
    {
        $this->authorize('view', $inspiration->orderItem);

        return Storage::disk($inspiration->disk)->response($inspiration->path);
    }

    public function destroy(OrderItemInspiration $inspiration): RedirectResponse
    {
        $this->authorize('update', $inspiration->orderItem);

        Storage::disk($inspiration->disk)->delete($inspiration->path);
        $inspiration->delete();

        return back();
    }
}
