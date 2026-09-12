<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            MeasurementFieldSeeder::class,
            GarmentTypeSeeder::class,
            FabricSeeder::class,
            GarmentOptionSeeder::class,
            CommerceSettingsSeeder::class,
            CmsSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
