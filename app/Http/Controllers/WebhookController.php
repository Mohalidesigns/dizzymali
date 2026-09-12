<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payments\HandleGatewayWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Gateway webhooks.
 *
 * These routes are exempt from CSRF — a gateway has no session and no token —
 * which is exactly why the signature check inside the gateway is the only thing
 * standing between this endpoint and a stranger marking orders paid.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly HandleGatewayWebhook $handle) {}

    public function __invoke(Request $request, string $gateway): Response
    {
        $accepted = $this->handle->handle(
            $gateway,
            $request->getContent(),
            $request->headers->all(),
        );

        // A rejected signature returns 401 with no body. Gateways retry on 5xx,
        // and there is nothing to retry here; a forged request should also learn
        // nothing about why it was refused.
        return response('', $accepted ? 200 : 401);
    }
}
