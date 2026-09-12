# Going live — what is waiting on you

Everything below is built, wired and tested. Each one switches on with configuration, not
code. This is the checklist, in the order the lead times demand.

---

## 1. WhatsApp — start this first

Meta's template approval takes days, and it is the longest lead time on this list. Nothing
else here blocks on anyone but you.

**Today:** apply for WhatsApp Business Cloud API access and submit five message templates.
The application refers to them by key, so call them whatever Meta will approve:

| Key | What it says |
|---|---|
| `order_submitted` | We have your order |
| `payment_received` | Payment received, balance if any |
| `stage_changed` | Your garment has reached a new stage |
| `progress_photo` | There is a new photograph |
| `order_shipped` | On its way, with tracking |

**When approved**, in `.env`:

```dotenv
WHATSAPP_ENABLED=true
WHATSAPP_DRIVER=cloud_api
WHATSAPP_PHONE_NUMBER_ID=...
WHATSAPP_ACCESS_TOKEN=...
WHATSAPP_TPL_ORDER_SUBMITTED=<whatever Meta approved>
```

Until then every WhatsApp message is written to `storage/logs` instead of sent — the full
path runs, so you can read exactly what customers would have received.

**If you would rather use something else**, implement `App\Domain\Messaging\WhatsAppDriver`
(three methods) and name it in `config/notifications.php`. No calling code changes.

---

## 2. Payments

**Paystack** — primary.

```dotenv
PAYSTACK_SECRET_KEY=sk_live_...
PAYSTACK_PUBLIC_KEY=pk_live_...
```

Then point a webhook at `https://yourdomain/webhooks/paystack` in the Paystack dashboard.

**Flutterwave** — the international fallback, complete and waiting.

```dotenv
FLUTTERWAVE_SECRET_KEY=...
FLUTTERWAVE_PUBLIC_KEY=...
FLUTTERWAVE_SECRET_HASH=...
```

Webhook: `https://yourdomain/webhooks/flutterwave`. The secret hash is what Flutterwave sends
back on every webhook; the application refuses anything that does not match it.

Until credentials are in, `Admin → Payments` shows each gateway as "awaiting credentials",
and the checkout page tells the customer plainly rather than failing at the card form.

**Bank transfer** works now, with no gateway at all:

```dotenv
PAYMENTS_BANK_ACCOUNT_NAME=
PAYMENTS_BANK_ACCOUNT_NUMBER=
PAYMENTS_BANK_NAME=
```

Staff confirm receipt from the order screen, which writes a proper payment row with an audit
trail rather than somebody editing a total.

**Test both with sandbox keys before going live.** Place a real order, pay it, and watch the
order reach `paid`.

---

## 3. Email

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="hello@dizzymali.com"
```

DNS matters more than the credentials: **SPF, DKIM and DMARC on the sending domain**, or a
meaningful share of your diaspora customers will never see an order confirmation. Propagation
is slow — do it before you need it.

While `MAIL_MAILER=log`, every message is written to `storage/logs/laravel.log`. Read a few
before you switch over.

---

## 4. Photography

Every garment and fabric currently shows a generated placeholder — a coloured panel with the
name on it and a small "Placeholder" label. It is deterministic, so the same fabric looks the
same everywhere, and it means the storefront reads as designed rather than broken.

**To replace them:** `Admin → Photography` lists every record with no photograph. Upload,
give it alt text, and it replaces the placeholder. Derivatives (AVIF, WebP and JPEG at five
widths) are generated on the queue.

Alt text is required at upload, deliberately. Asking later means it never gets written.

**Shot list, in the order it matters:**

1. Four garment heroes — Agbada, Kaftan, Jalabiya, Danshiki, worn, full length.
2. Fifteen fabric swatches, flat, evenly lit, colour-accurate. These carry the whole sale.
3. Lookbook, for the homepage carousel.

When everything is shot, turn the label off:

```dotenv
MEDIA_PLACEHOLDER_BADGE=false
```

---

## 5. Your real numbers

Still placeholders. They are internally consistent, so the pricing engine can be exercised,
but they are not prices to sell at.

| What | Where |
|---|---|
| Per-yard price per fabric | `Admin → Fabrics` |
| Sewing cost per garment | `Admin → Garments & yardage` |
| Yardage rules by size | `Admin → Garments & yardage` |
| Shipping rates per zone | `Admin → Shipping` |
| FX rates and margin | `Admin → Currencies` |

FX rates are set by hand and carry a margin to absorb naira volatility. To refresh them
daily instead, set `FX_DRIVER` and `FX_ENDPOINT` and schedule `php artisan fx:refresh`.
Either way, **a rate change never affects an order already placed** — the rate is frozen onto
the order at submission.

---

## 6. Infrastructure

```bash
php artisan queue:work --queue=notifications,media,default
php artisan schedule:work      # or one cron entry running schedule:run every minute
php artisan storage:link       # so uploaded catalogue images are served
```

Notifications and image processing both run on the queue. **Without a queue worker running,
no email is sent and no image is processed.** This is the single most common way a Laravel
deployment appears to work and quietly does not.

---

## Before the first real order

- [ ] Paystack live keys in, webhook registered, one sandbox order paid end to end
- [ ] SPF, DKIM and DMARC verified; a test email received in a real inbox
- [ ] Queue worker running under a supervisor, and restarting on deploy
- [ ] `php artisan storage:link` run on the server
- [ ] Real prices entered for every fabric and garment
- [ ] Shipping rates for your three main destinations
- [ ] Backups running, and a restore actually tested — an untested backup is a rumour
