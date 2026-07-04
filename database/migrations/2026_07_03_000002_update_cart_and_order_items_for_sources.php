<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateCartItems();
        $this->updateOrderItems();
    }

    public function down(): void
    {
        //
    }

    private function updateCartItems(): void
    {
        if (! Schema::hasTable('cart_items') || Schema::hasColumn('cart_items', 'item_source')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $this->dropMysqlForeignKeyIfExists('cart_items', 'cart_items_product_id_foreign');
            $this->dropMysqlIndexIfExists('cart_items', 'cart_items_cart_id_product_id_unique');
            DB::statement('ALTER TABLE cart_items MODIFY product_id VARCHAR(191) NOT NULL');
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->string('item_source')->default('Eclise')->after('product_id')->index();
            });
            DB::statement("UPDATE cart_items SET product_id = CONCAT('ecl', product_id) WHERE product_id REGEXP '^[0-9]+$'");
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->unique(['cart_id', 'item_source', 'product_id']);
            });

            return;
        }

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->string('item_source')->default('Eclise')->after('product_id')->index();
        });
    }

    private function updateOrderItems(): void
    {
        if (! Schema::hasTable('order_items') || Schema::hasColumn('order_items', 'item_source')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $this->dropMysqlForeignKeyIfExists('order_items', 'order_items_product_id_foreign');
            DB::statement('ALTER TABLE order_items MODIFY product_id VARCHAR(191) NULL');
            Schema::table('order_items', function (Blueprint $table): void {
                $table->string('item_source')->default('Eclise')->after('product_id')->index();
            });
            DB::statement("UPDATE order_items SET product_id = CONCAT('ecl', product_id) WHERE product_id REGEXP '^[0-9]+$'");

            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('item_source')->default('Eclise')->after('product_id')->index();
        });
    }

    private function dropMysqlForeignKeyIfExists(string $table, string $constraint): void
    {
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();

        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }

    private function dropMysqlIndexIfExists(string $table, string $index): void
    {
        $exists = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();

        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP INDEX {$index}");
        }
    }
};
