<?php

declare(strict_types=1);

use App\Actions\Measurements\SaveMeasurementProfile;
use App\Models\User;
use Database\Seeders\MeasurementFieldSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MeasurementFieldSeeder::class]);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('customer');
});

it('stores measurements in inches whatever the customer typed in', function () {
    $profile = app(SaveMeasurementProfile::class)->handle($this->user, [
        'name' => 'Metric',
        'unit' => 'cm',
        'values' => ['chest' => 106.68], // exactly 42 inches
    ]);

    expect((float) $profile->values->first()->value_inches)->toBe(42.0);
});

it('catches a typo before the fabric is cut', function () {
    // A 12-inch chest is not a small man, it is a slipped decimal point.
    app(SaveMeasurementProfile::class)->handle($this->user, [
        'name' => 'Typo',
        'unit' => 'in',
        'values' => ['chest' => 12],
    ]);
})->throws(ValidationException::class);

it('explains which measurement is wrong and what range it expects', function () {
    try {
        app(SaveMeasurementProfile::class)->handle($this->user, [
            'name' => 'Typo',
            'unit' => 'in',
            'values' => ['chest' => 400],
        ]);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('values.chest')
            ->and($e->errors()['values.chest'][0])->toContain('Chest');

        return;
    }

    $this->fail('An out-of-range chest should have been rejected.');
});

it('makes the first profile the default without being asked', function () {
    $profile = app(SaveMeasurementProfile::class)->handle($this->user, [
        'name' => 'First',
        'unit' => 'in',
        'values' => ['chest' => 40],
    ]);

    expect($profile->is_default)->toBeTrue();
});

it('moves the default when a new profile claims it', function () {
    $first = app(SaveMeasurementProfile::class)->handle($this->user, [
        'name' => 'First', 'unit' => 'in', 'values' => ['chest' => 40],
    ]);

    $second = app(SaveMeasurementProfile::class)->handle($this->user, [
        'name' => 'Second', 'unit' => 'in', 'is_default' => true, 'values' => ['chest' => 44],
    ]);

    expect($second->fresh()->is_default)->toBeTrue()
        ->and($first->fresh()->is_default)->toBeFalse();
});

it('rejects an out-of-range value over HTTP with a 422-style error', function () {
    $this->actingAs($this->user)
        ->post('/measurements', [
            'name' => 'Bad', 'unit' => 'in', 'values' => ['chest' => 5],
        ])
        ->assertSessionHasErrors('values.chest');
});
