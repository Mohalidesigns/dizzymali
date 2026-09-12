<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function store(StoreAddressRequest $request): RedirectResponse
    {
        $this->authorize('create', Address::class);

        $address = $request->user()->addresses()->create($request->validated());

        if ($request->boolean('is_default') || $request->user()->addresses()->count() === 1) {
            $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->forceFill(['is_default' => true])->save();
        }

        return back()->with('success', 'Address saved.');
    }

    public function update(StoreAddressRequest $request, Address $address): RedirectResponse
    {
        $this->authorize('update', $address);

        $address->update($request->validated());

        return back()->with('success', 'Address updated.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('delete', $address);

        $address->delete();

        return back()->with('success', 'Address removed.');
    }
}
