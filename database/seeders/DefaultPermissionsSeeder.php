<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the full permission catalogue.
 *
 * The payment and procurement permission names were originally seeded by their migrations, so
 * they must be reseeded here whenever the permissions table is cleared.
 */
class DefaultPermissionsSeeder extends Seeder
{
    public const ROLE_PERMISSIONS = ['admin', 'customer'];

    public const CATALOGUE = [
        'invoices.cancel',
        'invoices.create',
        'invoices.issue',
        'invoices.print',
        'invoices.view',
        'mobilesentrix.buffer.view',
        'mobilesentrix.orders.create',
        'mobilesentrix.orders.receive',
        'mobilesentrix.orders.return',
        'mobilesentrix.orders.update',
        'mobilesentrix.orders.view',
        'payments.cancel',
        'payments.create',
        'payments.export',
        'payments.gateway.payload.view',
        'payments.reconcile',
        'payments.record.manual',
        'payments.refund.approve',
        'payments.refund.process',
        'payments.refund.request',
        'payments.reject.interac',
        'payments.settings.manage',
        'payments.verify.interac',
        'payments.view',
        'payments.view.all',
        'payments.view.repair',
        'payments.view.shop',
        'payments.webhooks.retry',
        'payments.webhooks.view',
        'receipts.print',
        'receipts.view',
    ];

    public function run(): void
    {
        $admin = Permission::query()->updateOrCreate(['name' => 'admin'], ['status' => 'active']);
        $customer = Permission::query()->updateOrCreate(['name' => 'customer'], ['status' => 'active']);

        foreach (self::CATALOGUE as $name) {
            Permission::query()->updateOrCreate(['name' => $name], ['status' => 'active']);
        }

        User::query()
            ->whereNull('permission_id')
            ->where('role', 'admin')
            ->update(['permission_id' => $admin->id, 'status' => 'active']);

        User::query()
            ->whereNull('permission_id')
            ->update(['permission_id' => $customer->id, 'status' => 'active']);
    }
}
