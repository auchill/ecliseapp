<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $legacyColumns = [
        'quote_number',
        'customer_name',
        'email',
        'phone_number',
        'converted_to_booking',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('quotes')) {
            return;
        }

        $this->assertNoMissingCustomerIds();

        if (Schema::hasColumn('quotes', 'converted_to_repair')) {
            DB::table('quotes')->whereNull('converted_to_repair')->update(['converted_to_repair' => false]);
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildQuotesForSqlite();

            return;
        }

        $this->dropIndexIfExists('quotes', 'quotes_quote_number_unique');

        $columns = $this->existingColumns('quotes', $this->legacyColumns);

        if ($columns !== []) {
            Schema::table('quotes', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }

        Schema::table('quotes', function (Blueprint $table): void {
            if (Schema::hasColumn('quotes', 'customer_id')) {
                $table->foreignId('customer_id')->nullable(false)->change();
            }

            if (Schema::hasColumn('quotes', 'converted_to_repair')) {
                $table->boolean('converted_to_repair')->default(false)->nullable(false)->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quotes')) {
            return;
        }

        // Rollback recreates schema only. Restore the pre-cleanup backup for removed historical values.
        Schema::table('quotes', function (Blueprint $table): void {
            if (! Schema::hasColumn('quotes', 'quote_number')) {
                $table->string('quote_number')->nullable()->after('customer_id');
            }

            if (! Schema::hasColumn('quotes', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('quote_number');
            }

            if (! Schema::hasColumn('quotes', 'email')) {
                $table->string('email')->nullable()->after('customer_name');
            }

            if (! Schema::hasColumn('quotes', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('email');
            }

            if (! Schema::hasColumn('quotes', 'converted_to_booking')) {
                $table->boolean('converted_to_booking')->nullable()->after('admin_note');
            }
        });

        if (! $this->indexExists('quotes', 'quotes_quote_number_unique')) {
            Schema::table('quotes', function (Blueprint $table): void {
                $table->unique('quote_number', 'quotes_quote_number_unique');
            });
        }

        Schema::table('quotes', function (Blueprint $table): void {
            if (Schema::hasColumn('quotes', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->change();
            }
        });
    }

    private function assertNoMissingCustomerIds(): void
    {
        if (! Schema::hasColumn('quotes', 'customer_id')) {
            throw new RuntimeException('Cannot remove legacy quote identity columns before quotes.customer_id exists.');
        }

        if (DB::table('quotes')->whereNull('customer_id')->exists()) {
            throw new RuntimeException('Cannot remove legacy quote identity columns while quotes without customer_id exist.');
        }
    }

    private function rebuildQuotesForSqlite(): void
    {
        $columns = [
            'id',
            'customer_id',
            'device_type_id',
            'device_model',
            'issue_category_id',
            'preferred_date',
            'preferred_time',
            'device_image',
            'issue_description',
            'status',
            'admin_note',
            'converted_to_repair',
            'created_at',
            'updated_at',
            'product_brand_id',
            'product_model_id',
        ];

        Schema::disableForeignKeyConstraints();
        Schema::create('quotes_stage_b2', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('device_type_id')->nullable()->constrained('repair_device_types')->nullOnDelete();
            $table->string('device_model')->nullable();
            $table->foreignId('issue_category_id')->nullable()->constrained('issue_categories')->nullOnDelete();
            $table->date('preferred_date')->nullable();
            $table->string('preferred_time')->nullable();
            $table->string('device_image')->nullable();
            $table->text('issue_description');
            $table->string('status')->default('pending');
            $table->text('admin_note')->nullable();
            $table->boolean('converted_to_repair')->default(false);
            $table->timestamps();
            $table->foreignId('product_brand_id')->nullable()->constrained('product_brands')->nullOnDelete();
            $table->foreignId('product_model_id')->nullable()->constrained('product_models')->nullOnDelete();

            $table->index(['status', 'created_at']);
        });

        DB::statement('INSERT INTO quotes_stage_b2 ('.implode(', ', $columns).') SELECT '.implode(', ', $columns).' FROM quotes');
        Schema::drop('quotes');
        Schema::rename('quotes_stage_b2', 'quotes');
        Schema::enableForeignKeyConstraints();
    }

    private function existingColumns(string $table, array $columns): array
    {
        return array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column),
        ));
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index): void {
            $table->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->contains(fn (array $details): bool => ($details['name'] ?? null) === $index);
        } catch (Throwable) {
            return false;
        }
    }
};
