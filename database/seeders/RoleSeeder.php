<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'orders.view', 'orders.manage', 'orders.advance', 'orders.refund',
            'catalogue.view', 'catalogue.manage',
            'pricing.manage', 'currencies.manage', 'shipping.manage',
            'customers.view', 'customers.manage',
            'measurements.review',
            'cms.manage', 'media.manage',
            'reports.view',
            'staff.manage', 'settings.manage',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $matrix = [
            UserRole::Customer->value => [],
            UserRole::Tailor->value => ['orders.view', 'orders.advance', 'catalogue.view'],
            UserRole::Staff->value => [
                'orders.view', 'orders.manage', 'orders.advance',
                'catalogue.view', 'customers.view', 'measurements.review',
                'cms.manage', 'media.manage', 'reports.view',
            ],
            UserRole::Admin->value => array_diff($permissions, ['staff.manage']),
            UserRole::SuperAdmin->value => $permissions,
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(array_values($grants));
        }
    }
}
