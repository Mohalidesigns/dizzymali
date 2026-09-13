<?php

declare(strict_types=1);

use App\Domain\Media\ImageSupport;
use App\Jobs\ProcessMediaAsset;
use App\Models\FabricVariant;
use App\Models\GarmentType;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Database\Seeders\CommerceSettingsSeeder;
use Database\Seeders\FabricSeeder;
use Database\Seeders\GarmentTypeSeeder;
use Database\Seeders\MeasurementFieldSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Photography uploads
|--------------------------------------------------------------------------
| An upload is a two-part operation: the file lands in the request, and the
| derivatives are generated later on the `media` queue. Only a processed
| asset is served, so the window in between is a state the admin screen has
| to report — the original defect here was that it reported nothing at all,
| which is indistinguishable from the upload having failed.
*/

beforeEach(function () {
    $this->seed([RoleSeeder::class, MeasurementFieldSeeder::class, GarmentTypeSeeder::class, FabricSeeder::class, CommerceSettingsSeeder::class]);

    Storage::fake('public');

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');
});

it('stores the upload and queues the pipeline', function () {
    Queue::fake();

    $garment = GarmentType::query()->firstOrFail();

    $this->actingAs($this->admin)
        ->post('/admin/media', [
            'attachable_type' => 'garment-type',
            'attachable_id' => $garment->id,
            'collection' => 'hero',
            'alt_text' => 'An agbada in ivory linen',
            'file' => UploadedFile::fake()->image('agbada.jpg', 1200, 1500),
        ])
        ->assertRedirect();

    $asset = MediaAsset::query()->sole();

    expect($asset->processing_status)->toBe('pending');
    expect($asset->collection)->toBe('hero');
    Storage::disk('public')->assertExists($asset->path);
    Queue::assertPushed(ProcessMediaAsset::class);
});

it('reports an upload that is still waiting on the queue', function () {
    Queue::fake();

    $garment = GarmentType::query()->firstOrFail();

    $this->actingAs($this->admin)->post('/admin/media', [
        'attachable_type' => 'garment-type',
        'attachable_id' => $garment->id,
        'collection' => 'hero',
        'alt_text' => 'An agbada in ivory linen',
        'file' => UploadedFile::fake()->image('agbada.jpg', 1200, 1500),
    ]);

    // The regression: the tile derived its status from primaryMedia(), which
    // only ever returns a `ready` asset, so a pending upload showed the
    // placeholder with no status and no way to tell what had gone wrong.
    $this->actingAs($this->admin)->get('/admin/media')
        ->assertInertia(fn ($page) => $page
            ->where('garmentTypes.0.processing_status', 'pending')
            ->whereNot('garmentTypes.0.preview_url', null));
});

it('counts an upload left on the queue as stalled', function () {
    Queue::fake();

    $garment = GarmentType::query()->firstOrFail();

    $this->actingAs($this->admin)->post('/admin/media', [
        'attachable_type' => 'garment-type',
        'attachable_id' => $garment->id,
        'collection' => 'hero',
        'alt_text' => 'An agbada in ivory linen',
        'file' => UploadedFile::fake()->image('agbada.jpg', 1200, 1500),
    ]);

    // Minutes in `pending` means nothing is working the media queue. Reporting
    // that is the difference between a broken screen and a missing worker.
    MediaAsset::query()->sole()->forceFill(['created_at' => now()->subMinutes(10)])->save();

    $this->actingAs($this->admin)->get('/admin/media')
        ->assertInertia(fn ($page) => $page->where('summary.stalled', 1));
});

it('serves the derivatives once the pipeline has run', function () {
    $garment = GarmentType::query()->firstOrFail();

    $this->actingAs($this->admin)->post('/admin/media', [
        'attachable_type' => 'garment-type',
        'attachable_id' => $garment->id,
        'collection' => 'hero',
        'alt_text' => 'An agbada in ivory linen',
        'file' => UploadedFile::fake()->image('agbada.jpg', 1200, 1500),
    ]);

    $asset = MediaAsset::query()->sole();

    (new ProcessMediaAsset($asset->id))->handle();

    $asset->refresh();

    expect($asset->processing_status)->toBe('ready');
    expect($asset->derivatives)->not->toBeEmpty();

    // The storefront must be handed resized, EXIF-stripped derivatives, never
    // the original — and in whatever format this server could actually encode.
    // A stock XAMPP GD has no WebP, so the JPEG set has to be enough.
    $image = $garment->fresh()->imageFor('hero');

    expect($image['is_placeholder'])->toBeFalse()
        ->and($image['src'])->not->toContain($asset->path)
        ->and($image['src'])->toMatch('/-\d+\.jpg$/')
        ->and($image['srcset'])->not->toBe('')
        ->and($image['srcset'])->toContain(' 320w');

    // No status to report once it is ready, and no pending preview either.
    $this->actingAs($this->admin)->get('/admin/media')
        ->assertInertia(fn ($page) => $page
            ->where('garmentTypes.0.processing_status', 'ready')
            ->where('garmentTypes.0.preview_url', null));
});

it('lets staff preview the original before it has been processed', function () {
    Queue::fake();

    $variant = FabricVariant::query()->firstOrFail();

    $this->actingAs($this->admin)->post('/admin/media', [
        'attachable_type' => 'fabric-variant',
        'attachable_id' => $variant->id,
        'collection' => 'swatch',
        'alt_text' => 'Navy linen swatch',
        'file' => UploadedFile::fake()->image('navy.jpg', 800, 800),
    ]);

    $asset = MediaAsset::query()->sole();

    $this->actingAs($this->admin)->get("/admin/media/{$asset->id}/preview")->assertOk();
});

it('puts a failed asset back on the queue', function () {
    Queue::fake();

    $garment = GarmentType::query()->firstOrFail();

    $this->actingAs($this->admin)->post('/admin/media', [
        'attachable_type' => 'garment-type',
        'attachable_id' => $garment->id,
        'collection' => 'hero',
        'alt_text' => 'An agbada in ivory linen',
        'file' => UploadedFile::fake()->image('agbada.jpg', 1200, 1500),
    ]);

    $asset = MediaAsset::query()->sole();
    $asset->forceFill(['processing_status' => 'failed', 'processing_error' => 'GD ran out of memory'])->save();

    $this->actingAs($this->admin)->post("/admin/media/{$asset->id}/retry")->assertRedirect();

    expect($asset->fresh()->processing_status)->toBe('pending');
    expect($asset->fresh()->processing_error)->toBeNull();
});

it('catches up uploads the queue never ran', function () {
    Queue::fake();

    $garment = GarmentType::query()->firstOrFail();

    $this->actingAs($this->admin)->post('/admin/media', [
        'attachable_type' => 'garment-type',
        'attachable_id' => $garment->id,
        'collection' => 'hero',
        'alt_text' => 'An agbada in ivory linen',
        'file' => UploadedFile::fake()->image('agbada.jpg', 1200, 1500),
    ]);

    $this->artisan('media:process')->assertSuccessful();

    expect(MediaAsset::query()->sole()->processing_status)->toBe('ready');
});

it('refuses an image format this server cannot decode, with a reason', function () {
    Queue::fake();

    $variant = FabricVariant::query()->firstOrFail();

    // A minimal RIFF/WEBP container: enough for the MIME sniffer to call it
    // image/webp, which is all the upload validation should be looking at.
    $webp = 'RIFF'.pack('V', 4).'WEBPVP8 ';

    $response = $this->actingAs($this->admin)->post('/admin/media', [
        'attachable_type' => 'fabric-variant',
        'attachable_id' => $variant->id,
        'collection' => 'swatch',
        'alt_text' => 'Navy linen swatch',
        'file' => UploadedFile::fake()->createWithContent('navy.webp', $webp),
    ]);

    if (ImageSupport::canDecode('image/webp')) {
        // This GD reads WebP, so the upload is accepted and queued as usual.
        $response->assertSessionHasNoErrors();
        expect(MediaAsset::query()->count())->toBe(1);
    } else {
        // Previously this was accepted, then failed on the queue with "could
        // not be decoded", leaving a red tile and a Retry that never works.
        $response->assertSessionHasErrors('file');
        expect(session('errors')->first('file'))->toContain('WebP');
        expect(MediaAsset::query()->count())->toBe(0);
        Storage::disk('public')->assertDirectoryEmpty('media');
    }
});

it('offers the file picker only the formats this server can decode', function () {
    $this->actingAs($this->admin)->get('/admin/media')
        ->assertInertia(fn ($page) => $page
            ->where('accepts', implode(',', ImageSupport::decodableMimes())));
});

/*
|--------------------------------------------------------------------------
| Two crashes the log turned up while tracing the upload
|--------------------------------------------------------------------------
*/

it('lists orders in the admin without a missing-attribute crash', function () {
    // OrderResource reads garmentType->slug; the index eager-loaded id and name
    // only, and strict mode threw MissingAttributeException on every render.
    $customer = User::factory()->create(['email_verified_at' => now()]);
    $customer->assignRole('customer');

    $order = Order::factory()->for($customer)->submitted()->create();
    OrderItem::factory()->for($order)->create();

    $this->actingAs($this->admin)->get('/admin/orders')->assertOk();
});

it('opens the measurement review queue', function () {
    // authorize('review', MeasurementProfile::class) called a policy method that
    // required an instance, so the queue 500'd with ArgumentCountError.
    $this->actingAs($this->admin)->get('/admin/measurement-reviews')->assertOk();
});
