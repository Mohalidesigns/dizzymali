<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string,mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'unit_preference' => $user->unit_preference,
                    'preferred_currency' => $user->preferred_currency,
                    'country_code' => $user->country_code,
                    'is_back_office' => $user->isBackOffice(),
                    'is_admin' => $user->isAdmin(),
                    'roles' => $user->getRoleNames(),
                ],
            ],

            'currencies' => fn () => Currency::active()
                ->orderBy('sort_order')
                ->get(['code', 'symbol', 'decimals'])
                ->map(fn (Currency $c) => [
                    'code' => $c->code,
                    'symbol' => $c->symbol,
                    'decimals' => $c->decimals,
                ]),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
