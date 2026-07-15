<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CUSTOMER_SUPPLIED_KEY = 'customer_supplied';

    private const CUSTOMER_SUPPLIED_LABEL = 'I Have the Parts';

    private const EDITABLE_STATUSES = ['open', 'awaiting_customer'];

    public function up(): void
    {
        if (! Schema::hasTable('repair_part_groups') || ! Schema::hasTable('repair_part_options')) {
            return;
        }

        Schema::table('repair_part_options', function (Blueprint $table): void {
            if (! Schema::hasColumn('repair_part_options', 'option_type')) {
                $table->string('option_type', 50)->default('part')->after('repair_part_group_id')->index();
            }

            if (! Schema::hasColumn('repair_part_options', 'is_system_option')) {
                $table->boolean('is_system_option')->default(false)->after('option_type')->index();
            }

            if (! Schema::hasColumn('repair_part_options', 'system_option_key')) {
                $table->string('system_option_key', 80)->nullable()->after('is_system_option');
            }
        });

        DB::table('repair_part_options')->whereNull('option_type')->update(['option_type' => 'part']);
        DB::table('repair_part_options')->whereNull('is_system_option')->update(['is_system_option' => false]);

        if (Schema::hasColumn('repair_part_groups', 'is_required')) {
            DB::table('repair_part_groups')->where('is_active', true)->update(['is_required' => true]);
        }

        $this->normalizeExistingEditableCustomerSuppliedOptions();
        $this->backfillEditableGroups();
        $this->ensureIndex('repair_part_options', 'repair_part_options_group_system_unique', ['repair_part_group_id', 'system_option_key'], true);
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_part_options')) {
            return;
        }

        if ($this->indexExists('repair_part_options', 'repair_part_options_group_system_unique')) {
            Schema::table('repair_part_options', function (Blueprint $table): void {
                $table->dropUnique('repair_part_options_group_system_unique');
            });
        }

        Schema::table('repair_part_options', function (Blueprint $table): void {
            foreach (['system_option_key', 'is_system_option', 'option_type'] as $column) {
                if (Schema::hasColumn('repair_part_options', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function normalizeExistingEditableCustomerSuppliedOptions(): void
    {
        $editableGroupIds = $this->editableGroupIds();

        if ($editableGroupIds->isEmpty()) {
            return;
        }

        $editableGroupIds->chunk(500)->each(function ($groupIds): void {
            $candidates = DB::table('repair_part_options')
                ->whereIn('repair_part_group_id', $groupIds->all())
                ->whereNull('source_type')
                ->whereNull('source_id')
                ->whereIn(DB::raw('LOWER(name_snapshot)'), ['i have the parts', 'come with parts'])
                ->orderBy('id')
                ->get()
                ->groupBy('repair_part_group_id');

            foreach ($candidates as $options) {
                $systemOption = $options->first();

                DB::table('repair_part_options')
                    ->where('id', $systemOption->id)
                    ->update($this->systemOptionPayload([
                        'created_at' => $systemOption->created_at,
                    ]));

                $duplicateIds = $options->skip(1)->pluck('id');

                if ($duplicateIds->isNotEmpty()) {
                    DB::table('repair_part_options')
                        ->whereIn('id', $duplicateIds->all())
                        ->update([
                            'is_active' => false,
                            'is_primary' => false,
                            'system_option_key' => null,
                            'updated_at' => now(),
                        ]);
                }
            }
        });
    }

    private function backfillEditableGroups(): void
    {
        $this->editableGroupsQuery()
            ->select('repair_part_groups.id', 'repair_part_groups.proposal_version')
            ->orderBy('repair_part_groups.id')
            ->chunkById(500, function ($groups): void {
                foreach ($groups as $group) {
                    $exists = DB::table('repair_part_options')
                        ->where('repair_part_group_id', $group->id)
                        ->where('system_option_key', self::CUSTOMER_SUPPLIED_KEY)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('repair_part_options')->insert($this->systemOptionPayload([
                        'repair_part_group_id' => $group->id,
                        'proposal_version' => max(1, (int) $group->proposal_version),
                    ]));
                }
            }, 'repair_part_groups.id', 'id');
    }

    private function editableGroupIds()
    {
        return $this->editableGroupsQuery()->pluck('repair_part_groups.id');
    }

    private function editableGroupsQuery()
    {
        return DB::table('repair_part_groups')
            ->join('repair_conversations', 'repair_conversations.id', '=', 'repair_part_groups.repair_conversation_id')
            ->where('repair_part_groups.is_active', true)
            ->whereIn('repair_conversations.status', self::EDITABLE_STATUSES);
    }

    private function systemOptionPayload(array $overrides = []): array
    {
        $payload = [
            'option_type' => self::CUSTOMER_SUPPLIED_KEY,
            'is_system_option' => true,
            'system_option_key' => self::CUSTOMER_SUPPLIED_KEY,
            'source_type' => null,
            'source_id' => null,
            'sku_snapshot' => null,
            'name_snapshot' => self::CUSTOMER_SUPPLIED_LABEL,
            'description_snapshot' => 'Customer will provide this required part.',
            'quality_label' => null,
            'price_snapshot' => 0,
            'is_primary' => false,
            'sort_order' => 0,
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('repair_part_options', 'model_snapshot')) {
            $payload['model_snapshot'] = null;
        }

        if (Schema::hasColumn('repair_part_options', 'image_url_snapshot')) {
            $payload['image_url_snapshot'] = null;
        }

        if (! array_key_exists('created_at', $overrides)) {
            $payload['created_at'] = now();
        }

        return array_merge($payload, $overrides);
    }

    private function ensureIndex(string $tableName, string $indexName, array $columns, bool $unique = false): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName, $unique): void {
            $unique ? $table->unique($columns, $indexName) : $table->index($columns, $indexName);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            return collect(DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]))->isNotEmpty();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$tableName}')"))
                ->contains(fn ($index): bool => ($index->name ?? null) === $indexName);
        }

        return false;
    }
};
