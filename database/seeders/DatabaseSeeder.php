<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds a complete Eclise test dataset.
 *
 * Order matters: reference data and accounts first, then catalogue, then the transactional
 * scenarios, and finally procurement — which depends on the buffer rows that settling the shop
 * and repair payments produces.
 *
 * MobileSentrix catalogue data (parts, categories, devices, API settings) is never seeded here;
 * it comes from the MobileSentrix sync and is preserved by eclise:reset-app-data.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Model events are deliberately left enabled.
     *
     * Payment and invoice numbers are assigned in `created` hooks, and CartItem, OrderItem and
     * MobileSentrixBuffer enforce their invariants in `saving` hooks. Muting events with
     * WithoutModelEvents produces records with null numbers that skipped every validation.
     */
    public function run(): void
    {
        // Foundations
        $this->call(DefaultPermissionsSeeder::class);
        $this->call(PaymentSettingsSeeder::class);
        $this->call(ShippingSeeder::class);
        $this->call(CatalogTaxonomySeeder::class);
        $this->call(ProductLookupSeeder::class);
        $this->call(ReferenceDataSeeder::class);
        $this->call(EcliseMarkupSeeder::class);

        // Accounts
        $this->call(AdminUserSeeder::class);
        $this->call(CustomerSeeder::class);

        // Catalogue
        $this->call(ProductSeeder::class);

        // Transactional scenarios
        $this->call(ShopOrderSeeder::class);
        $this->call(RepairScenarioSeeder::class);
        $this->call(EngagementSeeder::class);

        // Depends on procurement requirements created by the settled payments above
        $this->call(ProcurementSeeder::class);
    }
}
