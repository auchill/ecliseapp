<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('cart_items', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('cart_id');
            }
            if (! Schema::hasColumn('cart_items', 'source_sku')) {
                $table->string('source_sku', 191)->nullable()->after('source_id');
            }
            if (! Schema::hasColumn('cart_items', 'source')) {
                $table->string('source', 32)->nullable()->after('source_sku');
            }
        });

        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('order_id');
            }
            if (! Schema::hasColumn('order_items', 'source_sku')) {
                $table->string('source_sku', 191)->nullable()->after('source_id');
            }
            if (! Schema::hasColumn('order_items', 'source')) {
                $table->string('source', 32)->nullable()->after('source_sku');
            }
            if (! Schema::hasColumn('order_items', 'item_name')) {
                $table->string('item_name')->nullable()->after('source');
            }
            if (! Schema::hasColumn('order_items', 'image_url')) {
                $table->longText('image_url')->nullable()->after('item_name');
            }
        });

        $this->backfillCartItems();
        $this->backfillOrderItems();

        if (! $this->indexExists('cart_items', 'cart_items_cart_id_index')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->index('cart_id');
            });
        }

        Schema::table('cart_items', function (Blueprint $table): void {
            if ($this->indexExists('cart_items', 'cart_items_cart_id_item_source_product_id_unique')) {
                $table->dropUnique('cart_items_cart_id_item_source_product_id_unique');
            }
            if ($this->indexExists('cart_items', 'cart_items_product_id_index')) {
                $table->dropIndex('cart_items_product_id_index');
            }
            if ($this->indexExists('cart_items', 'cart_items_item_source_index')) {
                $table->dropIndex('cart_items_item_source_index');
            }
            if (Schema::hasColumn('cart_items', 'product_id')) {
                $table->dropColumn('product_id');
            }
            if (Schema::hasColumn('cart_items', 'item_source')) {
                $table->dropColumn('item_source');
            }
            $table->unsignedBigInteger('source_id')->nullable(false)->change();
            $table->string('source_sku', 191)->nullable(false)->change();
            $table->string('source', 32)->nullable(false)->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            if ($this->indexExists('order_items', 'order_items_product_id_index')) {
                $table->dropIndex('order_items_product_id_index');
            }
            if ($this->indexExists('order_items', 'order_items_item_source_index')) {
                $table->dropIndex('order_items_item_source_index');
            }
            foreach (['product_id', 'item_source', 'product_name', 'sku'] as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
            $table->unsignedBigInteger('source_id')->nullable(false)->change();
            $table->string('source_sku', 191)->nullable(false)->change();
            $table->string('source', 32)->nullable(false)->change();
            $table->string('item_name')->nullable(false)->change();
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            if (! $this->indexExists('cart_items', 'cart_items_source_identity_unique')) {
                $table->unique(['cart_id', 'source', 'source_id', 'source_sku'], 'cart_items_source_identity_unique');
            }
            if (! $this->indexExists('cart_items', 'cart_items_source_lookup_index')) {
                $table->index(['source', 'source_id'], 'cart_items_source_lookup_index');
            }
        });

        Schema::table('order_items', function (Blueprint $table): void {
            if (! $this->indexExists('order_items', 'order_items_source_lookup_index')) {
                $table->index(['source', 'source_id'], 'order_items_source_lookup_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->string('product_id')->nullable()->index();
            $table->string('item_source')->default('Eclise')->index();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('product_id')->nullable()->index();
            $table->string('item_source')->default('Eclise')->index();
            $table->string('product_name')->nullable();
            $table->string('sku')->nullable();
        });

        DB::table('cart_items')->orderBy('id')->each(function ($item): void {
            DB::table('cart_items')->where('id', $item->id)->update([
                'product_id' => $item->source === 'Eclise' ? 'ecl'.$item->source_id : (string) $item->source_id,
                'item_source' => $item->source,
            ]);
        });

        DB::table('order_items')->orderBy('id')->each(function ($item): void {
            DB::table('order_items')->where('id', $item->id)->update([
                'product_id' => $item->source === 'Eclise' ? 'ecl'.$item->source_id : (string) $item->source_id,
                'item_source' => $item->source,
                'product_name' => $item->item_name,
                'sku' => $item->source_sku,
            ]);
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique('cart_items_source_identity_unique');
            $table->dropIndex('cart_items_source_lookup_index');
            $table->dropColumn(['source_id', 'source_sku', 'source']);
            $table->unique(['cart_id', 'item_source', 'product_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('order_items_source_lookup_index');
            $table->dropColumn(['source_id', 'source_sku', 'source', 'item_name', 'image_url']);
        });
    }

    private function backfillCartItems(): void
    {
        DB::table('cart_items')->orderBy('id')->chunkById(100, function ($items): void {
            foreach ($items as $item) {
                $identity = $this->resolveIdentity($item->item_source, $item->product_id, null);

                DB::table('cart_items')->where('id', $item->id)->update([
                    'source_id' => $identity['source_id'] ?: $item->id,
                    'source_sku' => $identity['source_sku'] ?: 'legacy-'.$item->id,
                    'source' => $identity['source'],
                ]);
            }
        });
    }

    private function backfillOrderItems(): void
    {
        DB::table('order_items')->orderBy('id')->chunkById(100, function ($items): void {
            foreach ($items as $item) {
                $identity = $this->resolveIdentity($item->item_source, $item->product_id, $item->sku);

                DB::table('order_items')->where('id', $item->id)->update([
                    'source_id' => $identity['source_id'] ?: $item->id,
                    'source_sku' => $identity['source_sku'] ?: 'legacy-'.$item->id,
                    'source' => $identity['source'],
                    'item_name' => $item->product_name ?: 'Unavailable item',
                    'image_url' => $identity['image_url'],
                ]);
            }
        });
    }

    private function resolveIdentity(?string $source, string|int|null $legacyId, ?string $legacySku): array
    {
        $source = strcasecmp((string) $source, 'Mobilesentrix') === 0 ? 'Mobilesentrix' : 'Eclise';

        if ($source === 'Mobilesentrix') {
            $device = DB::table('mobilesentrix_devices')
                ->where(function ($query) use ($legacyId, $legacySku): void {
                    if (is_numeric($legacyId)) {
                        $query->where('entity_id', (int) $legacyId);
                    } else {
                        $query->where('sku', (string) $legacyId);
                    }

                    if ($legacySku) {
                        $query->orWhere('sku', $legacySku);
                    }
                })
                ->first();

            return [
                'source' => $source,
                'source_id' => $device?->entity_id ?: (is_numeric($legacyId) ? (int) $legacyId : null),
                'source_sku' => $device?->sku ?: $legacySku ?: (string) $legacyId,
                'image_url' => $device?->image_url ?: '/images/brand/eclise-thumb-grey.png',
            ];
        }

        $sourceId = preg_replace('/^ecl/i', '', (string) $legacyId);
        $product = is_numeric($sourceId) ? DB::table('products')->find((int) $sourceId) : null;

        return [
            'source' => $source,
            'source_id' => is_numeric($sourceId) ? (int) $sourceId : null,
            'source_sku' => $product?->sku ?: $legacySku ?: (string) $legacyId,
            'image_url' => $product?->image_path ? '/storage/'.$product->image_path : '/images/brand/eclise-thumb-grey.png',
        ];
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $details): bool => ($details['name'] ?? null) === $index,
        );
    }
};
