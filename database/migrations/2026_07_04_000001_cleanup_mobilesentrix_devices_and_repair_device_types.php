<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->removeMobileSentrixDeviceTypeColumn();
        $this->renameRepairDeviceTypesTable();
        $this->cleanupGeneratedPartCategories();
        $this->backfillMobileSentrixDeviceModels();
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_device_types') && ! Schema::hasTable('device_types')) {
            Schema::rename('repair_device_types', 'device_types');
        }
    }

    private function removeMobileSentrixDeviceTypeColumn(): void
    {
        if (! Schema::hasTable('mobilesentrix_devices') || ! Schema::hasColumn('mobilesentrix_devices', 'device_type_id')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $this->dropMysqlForeignKeysForColumn('mobilesentrix_devices', 'device_type_id');
        }

        Schema::table('mobilesentrix_devices', function (Blueprint $table): void {
            if (Schema::hasColumn('mobilesentrix_devices', 'device_type_id')) {
                $table->dropColumn('device_type_id');
            }
        });
    }

    private function renameRepairDeviceTypesTable(): void
    {
        if (Schema::hasTable('device_types') && ! Schema::hasTable('repair_device_types')) {
            Schema::rename('device_types', 'repair_device_types');
        }

        if (! Schema::hasTable('repair_device_types')) {
            Schema::create('repair_device_types', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('status')->default('active');
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('repair_device_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('repair_device_types', 'code')) {
                $table->string('code')->nullable()->index()->after('slug');
            }

            if (! Schema::hasColumn('repair_device_types', 'source')) {
                $table->string('source')->nullable()->index()->after('code');
            }
        });
    }

    private function cleanupGeneratedPartCategories(): void
    {
        if (! Schema::hasTable('part_categories')) {
            return;
        }

        $query = DB::table('part_categories')
            ->where('name', 'like', 'MobileSentrix Category %');

        if (Schema::hasColumn('part_categories', 'raw_payload')) {
            $query->where(function ($query): void {
                $query->whereNull('raw_payload');

                if (DB::connection()->getDriverName() === 'mysql') {
                    $query->orWhereRaw('JSON_LENGTH(raw_payload) = 0');

                    return;
                }

                $query->orWhere('raw_payload', '')
                    ->orWhere('raw_payload', '[]')
                    ->orWhere('raw_payload', '{}');
            });
        }

        if (Schema::hasColumn('part_categories', 'slug')) {
            $query->where('slug', 'like', 'mobilesentrix-category-%');
        }

        $categoryIds = $query->pluck('id')->all();

        if ($categoryIds === []) {
            return;
        }

        if (Schema::hasTable('part_category_part')) {
            DB::table('part_category_part')->whereIn('category_id', $categoryIds)->delete();
        }

        if (Schema::hasTable('parts') && Schema::hasColumn('parts', 'part_category_id')) {
            DB::table('parts')->whereIn('part_category_id', $categoryIds)->update([
                'part_category_id' => null,
                'updated_at' => now(),
            ]);
        }

        DB::table('part_categories')->whereIn('id', $categoryIds)->delete();
    }

    private function backfillMobileSentrixDeviceModels(): void
    {
        if (! Schema::hasTable('mobilesentrix_devices') || ! Schema::hasColumn('mobilesentrix_devices', 'raw_payload')) {
            return;
        }

        DB::table('mobilesentrix_devices')
            ->where(function ($query): void {
                $query->whereNull('device_model_text')->orWhere('device_model_text', '');
            })
            ->whereNotNull('raw_payload')
            ->orderBy('id')
            ->select(['id', 'raw_payload'])
            ->chunkById(200, function ($devices): void {
                foreach ($devices as $device) {
                    $payload = is_string($device->raw_payload)
                        ? json_decode($device->raw_payload, true)
                        : (array) $device->raw_payload;

                    if (! is_array($payload)) {
                        continue;
                    }

                    $model = $this->firstPayloadText($payload, [
                        'device_model_text',
                        'model_text',
                        'device_name_text',
                        'device_model',
                        'model',
                        'model_name',
                        'product_model',
                        'product_model_text',
                    ]);

                    if (! filled($model)) {
                        continue;
                    }

                    DB::table('mobilesentrix_devices')->where('id', $device->id)->update([
                        'device_model_text' => $model,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function firstPayloadText(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if (is_array($value)) {
                $value = collect($value)
                    ->flatten()
                    ->filter(fn ($item): bool => is_scalar($item) && filled($item))
                    ->implode(', ');
            }

            if (filled($value)) {
                return mb_substr((string) $value, 0, 255);
            }
        }

        return null;
    }

    private function dropMysqlForeignKeysForColumn(string $table, string $column): void
    {
        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
