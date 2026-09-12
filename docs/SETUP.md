# DizzyMali — setup

Laravel 12 · PHP 8.3+ · MySQL 8 · React 18 + TypeScript via Inertia 2 · Tailwind 4

## First run

```bash
composer update          # composer.json gained Inertia, the spatie packages, Pest and Larastan
npm install

php artisan key:generate # only if APP_KEY is empty
php artisan migrate --seed
npm run build            # or `npm run dev` while working
```

`.env` is already pointed at MySQL:

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=dizzymali
```

`composer update` rather than `install`: the lock file in the repo predates the new
packages, so it has to be regenerated once. After that, `composer install` is correct.

## Tests

The suite runs against its own MySQL schema, created once:

```sql
CREATE DATABASE dizzymali_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
./vendor/bin/pest                      # 66 tests
./vendor/bin/pest --testsuite=Unit     # pricing, yardage, state machine — no database
./vendor/bin/pest --testsuite=Feature  # endpoints, authorisation, order flow
```

`phpunit.xml` points the `DB_DATABASE` env at `dizzymali_testing`; `RefreshDatabase` migrates
it per test.

## Quality gates

Nothing is done until all four are green.

```bash
./vendor/bin/pint            # formatting  (--test to check without writing)
./vendor/bin/phpstan analyse # Larastan level 6
./vendor/bin/pest            # PHP tests
npm run typecheck            # tsc --noEmit, no `any`
```

## Day-to-day

```bash
composer dev   # serve + queue worker + log tail + vite, all four together
```

Notifications and image processing both run on the queue, so a worker must be running or
neither happens:

```bash
php artisan queue:work --queue=notifications,media,default
php artisan schedule:work
php artisan storage:link    # once, so uploaded catalogue images are served
```

Image URLs are built from `APP_URL`, so it must match the host and port you actually open the
app on. With `php artisan serve` that is `APP_URL=http://127.0.0.1:8000`; leaving it at
`http://localhost` makes every processed image 404 while the placeholders still work.

WebP and AVIF derivatives are only generated when the server's GD build can encode them
(check `php -r 'print_r(gd_info());'`). Without them the pipeline serves JPEG only, which is
fine for development; production should have a GD or Imagick build with WebP support.

If an image has already been uploaded while no worker was running, it is stored but has no
derivatives, and only a processed asset is served — so the storefront keeps showing the
placeholder. `Admin -> Photography` reports this rather than staying silent about it, and
either of these clears it:

```bash
php artisan media:process                  # process everything still waiting, in this process
php artisan media:process --retry-failed   # and re-attempt the ones that errored
```

## Switching on the held integrations

Flutterwave, WhatsApp, SMTP and the product photography are all built and waiting on
credentials or content rather than code. `docs/GOING_LIVE.md` is the checklist, in the order
the lead times demand.

## Demo accounts

Seeded in `local` and `testing` only. Password is `password` for all of them.

| Email | Role |
|---|---|
| `admin@dizzymali.test` | super-admin |
| `staff@dizzymali.test` | staff |
| `tailor@dizzymali.test` | tailor |
| `adebayo@example.test` | customer, Nigeria, NGN |
| `ibrahim@example.test` | customer, UK, GBP |
| `yusuf@example.test` | customer, US, USD |

Each customer has a saved measurement profile and four orders sitting at different stages, so
the admin pipeline is populated on first login rather than empty.

## Where things live

```
app/Domain/Pricing/     QuoteCalculator, YardageCalculator — pure, no framework, no database
app/Domain/Orders/      OrderStateMachine — the only writer of orders.status
app/Domain/Currency/    CurrencyConverter
app/Domain/Shipping/    ShippingRater
app/Actions/            SaveDraftOrder, RecalculateQuote, SubmitOrder, AdvanceOrderStage,
                        SaveMeasurementProfile
app/Support/Money.php   Integer-kobo value object. Nothing here accepts a float.
app/Enums/              OrderStatus (16 states), CustomerStage (the 5 a customer sees)
app/Policies/           One per model, each with a denial test
resources/js/Pages/     storefront/ · admin/ · auth/
resources/js/Components/wizard/   The six steps and the live price panel
docs/adr/               Decisions with a cost to reverse
```

## The figures in the seeders are placeholders

Sewing costs, per-yard prices, yardage rules, shipping rates and FX rates are all invented.
They are internally consistent — an Agbada takes more cloth and more labour than a Danshiki —
so the engine can be exercised end to end, but they are not prices to sell at.

Replace them in `database/seeders/`, or edit them in the admin panel, which is where they are
meant to live: `Admin → Fabrics` for per-yard prices, `Admin → Garments & yardage` for sewing
costs and the size rules.
