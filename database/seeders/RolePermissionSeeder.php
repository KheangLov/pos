<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $entities = [
            'branch', 'cash_note', 'category', 'company', 'discount', 'floor_plan',
            'invoice', 'modifier', 'modifier_factor', 'modifier_group', 'order_item',
            'payment', 'product', 'product_variant', 'serial_number', 'shift',
            'stock_transaction', 'table', 'tax', 'user',
        ];

        $allPermissions = [];
        foreach ($entities as $entity) {
            foreach (['view', 'create', 'update', 'delete', 'restore', 'force_delete'] as $action) {
                $allPermissions[] = "{$action}_{$entity}";
            }
        }

        foreach ($allPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Operator-only, outside the per-entity CRUD matrix above: Telescope
        // shows every tenant's data at once, so this is granted to individual
        // people by hand, never to a role.
        Permission::firstOrCreate(['name' => 'view_telescope', 'guard_name' => 'web']);

        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $cashier = Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web']);
        $kitchen = Role::firstOrCreate(['name' => 'Kitchen', 'guard_name' => 'web']);

        // Scoped to the generated list rather than Permission::all() so that
        // operator-only permissions like view_telescope are never swept into
        // a customer's Admin role by a reseed.
        $admin->syncPermissions(Permission::whereIn('name', $allPermissions)->get());

        $manager->syncPermissions(array_filter($allPermissions, function ($perm) {
            return ! str_contains($perm, 'company') && ! str_contains($perm, 'force_delete');
        }));

        $cashier->syncPermissions(array_filter($allPermissions, function ($perm) {
            foreach (['invoice', 'order_item', 'payment', 'shift', 'cash_note'] as $needle) {
                if (str_contains($perm, $needle)) {
                    return ! str_contains($perm, 'force_delete') && ! str_contains($perm, 'delete');
                }
            }

            return str_starts_with($perm, 'view_') && (str_contains($perm, 'product') || str_contains($perm, 'category') || str_contains($perm, 'table'));
        }));

        $kitchen->syncPermissions([
            'view_invoice', 'view_order_item', 'update_order_item',
        ]);
    }
}
