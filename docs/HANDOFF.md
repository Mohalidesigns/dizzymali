# Handoff — 2026-09-12

Picking this up in Claude Code. Everything below is **uncommitted** in the working tree.

## What was wrong

Admin → Photography uploads looked like they were not saving. They were: six originals sit
under `storage/app/public/media/`. Three separate things:

1. **Nothing was working the `media` queue.** `QUEUE_CONNECTION=database`, and
   `ProcessMediaAsset` is what generates the derivatives and flips the asset to `ready`.
   `primaryMedia()` only ever returns a `ready` asset — deliberately, because an unprocessed
   original still carries its EXIF, and that includes where the photograph was taken. So with
   no worker the asset stays `pending` and the placeholder correctly keeps winning.
2. **`public/storage` did not exist.** Even once processed, every image would 404.
3. **The screen reported none of this.** `MediaAdminController::row()` read
   `processing_status` off `primaryMedia()`, which filters to `ready`, so a pending asset
   reported `null` and a successful upload looked identical to a failed one. That one was the
   real defect.

## Changed

- `app/Http/Controllers/Admin/MediaAdminController.php` — `row()` now reports the latest asset
  in the collection whatever its state (`asset_id`, `processing_status`, `processing_error`,
  `preview_url`); new `preview()` streams the original to staff off the disk rather than
  exposing an un-stripped file on the public disk; new `retry()`; `summary` gains `stalled`
  (pending for over two minutes) and `storage_linked`.
- `routes/web.php` — `admin.media.preview` (GET), `admin.media.retry` (POST).
- `resources/js/Pages/admin/Media.tsx` — shows the uploaded original with a Processing/Failed
  badge while it is in the pipeline, banners naming the stalled queue and the missing symlink,
  a Retry button, and `errors.file` (previously swallowed).
- `app/Console/Commands/ProcessPendingMedia.php` (new) — `media:process`, `--retry-failed`.
  An upload that happened while no worker was running should not need re-uploading.
- `app/Models/MediaAsset.php` — docblock gains `mime_type`, `size_bytes`, `width`, `height`,
  `processing_error`.
- `tests/Feature/MediaUploadTest.php` (new) — there were no media feature tests at all, which
  is why this shipped.
- `docs/SETUP.md` — the recovery steps.

Two unrelated crashes from the same log:

- `app/Policies/MeasurementProfilePolicy.php` + `MeasurementReviewController` — gained
  `reviewAny()`. `authorize('review', MeasurementProfile::class)` calls the ability with no
  model, and `review()` requires one, so `/admin/measurement-reviews` threw
  `ArgumentCountError`.
- `app/Http/Controllers/Admin/OrderAdminController.php` — index now eager-loads
  `items.garmentType:id,slug,name`. `OrderResource::mapItems()` reads `->slug`, and strict mode
  threw `MissingAttributeException` on every `/admin/orders` render.

## Done since (same day)

`storage:link` created and `media:process` run: all six assets are `ready`, none failed.
Keep a worker up from now on: `php artisan queue:work --queue=notifications,media,default`,
or just `composer dev`.

All gates run and green: Pest 138 passed, Larastan clean, Pint clean, `tsc --noEmit` clean.
There are still no Vitest files, so that gate is vacuous. Running the suite surfaced three
more defects, all fixed:

- `tests/TestCase.php` — `withoutVite()` in `setUp()`. Every Inertia page test 500ed with
  `ViteManifestNotFoundException` as soon as the dev server was stopped, because there is no
  `public/build`. The suite must not depend on `public/hot` existing.
- `app/Notifications/OrderNotification.php` — `$order` is no longer `readonly`. Queued
  notifications are rebuilt through `SerializesModels::__unserialize`, which assigns from the
  concrete subclass scope, and PHP 8.2 only lets a readonly property be initialised from its
  declaring class. Every queued order notification threw on the worker, and with the sync
  driver that 500ed `/order/{id}/submit` too, leaving orders stuck in Draft.
- `app/Models/Order.php` — `statusEvents()` now orders by `id` desc after `created_at`.
  Two stage changes in the same second came back in undefined order on MySQL.

Nothing is committed yet.
