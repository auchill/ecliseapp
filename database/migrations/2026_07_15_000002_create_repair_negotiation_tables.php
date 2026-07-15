<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_conversations')) {
            Schema::create('repair_conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('repair_id')->unique()->constrained('repairs')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 50)->default('open');
                $table->unsignedInteger('proposal_version')->default(0);
                $table->unsignedInteger('accepted_proposal_version')->nullable();
                $table->decimal('labour_amount', 12, 2)->default(0);
                $table->decimal('diagnostic_fee', 12, 2)->default(0);
                $table->decimal('service_fee', 12, 2)->default(0);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('selected_parts_subtotal', 12, 2)->default(0);
                $table->decimal('final_total', 12, 2)->default(0);
                $table->timestamp('last_message_at')->nullable();
                $table->timestamp('agreed_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index('customer_id');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('repair_messages')) {
            Schema::create('repair_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('repair_conversation_id')->constrained('repair_conversations')->cascadeOnDelete();
                $table->string('sender_type', 30);
                $table->unsignedBigInteger('sender_id')->nullable();
                $table->string('message_type', 50)->default('text');
                $table->text('message');
                $table->boolean('is_internal')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['repair_conversation_id', 'is_internal']);
                $table->index(['sender_type', 'sender_id']);
            });
        }

        if (! Schema::hasTable('repair_part_groups')) {
            Schema::create('repair_part_groups', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('repair_conversation_id')->constrained('repair_conversations')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->boolean('is_required')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedInteger('proposal_version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['repair_conversation_id', 'is_active']);
                $table->index('proposal_version');
            });
        }

        if (! Schema::hasTable('repair_part_options')) {
            Schema::create('repair_part_options', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('repair_part_group_id')->constrained('repair_part_groups')->cascadeOnDelete();
                $table->string('source_type', 80)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('sku_snapshot')->nullable();
                $table->string('name_snapshot');
                $table->text('description_snapshot')->nullable();
                $table->string('quality_label')->nullable();
                $table->decimal('price_snapshot', 12, 2);
                $table->boolean('is_primary')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedInteger('proposal_version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['repair_part_group_id', 'is_active']);
                $table->index(['source_type', 'source_id']);
            });
        }

        if (! Schema::hasTable('repair_part_selections')) {
            Schema::create('repair_part_selections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('repair_part_group_id')->constrained('repair_part_groups')->cascadeOnDelete();
                $table->foreignId('repair_part_option_id')->constrained('repair_part_options')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->timestamp('selected_at');
                $table->timestamps();

                $table->unique(['repair_part_group_id', 'customer_id'], 'repair_part_selection_unique_group_customer');
                $table->index('repair_part_option_id');
            });
        }

        if (! Schema::hasTable('repair_negotiation_events')) {
            Schema::create('repair_negotiation_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('repair_conversation_id')->constrained('repair_conversations')->cascadeOnDelete();
                $table->string('actor_type', 30)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('event_type', 80);
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['repair_conversation_id', 'event_type'], 'repair_events_conversation_type_idx');
                $table->index(['actor_type', 'actor_id']);
            });
        } else {
            $this->ensureIndex('repair_negotiation_events', 'repair_events_conversation_type_idx', ['repair_conversation_id', 'event_type']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_negotiation_events');
        Schema::dropIfExists('repair_part_selections');
        Schema::dropIfExists('repair_part_options');
        Schema::dropIfExists('repair_part_groups');
        Schema::dropIfExists('repair_messages');
        Schema::dropIfExists('repair_conversations');
    }

    private function ensureIndex(string $tableName, string $indexName, array $columns): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName, $columns): void {
            $table->index($columns, $indexName);
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
