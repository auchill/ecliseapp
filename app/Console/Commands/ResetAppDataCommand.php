<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears Eclise application data while preserving everything synced from the MobileSentrix API.
 *
 * Destructive and irreversible. Take a database backup first.
 */
class ResetAppDataCommand extends Command
{
    protected $signature = 'eclise:reset-app-data
        {--force : Required. Confirms the destructive operation.}
        {--pretend : Report what would be cleared without writing.}';

    protected $description = 'Truncate Eclise application tables, preserving MobileSentrix API data.';

    /**
     * Synced from the MobileSentrix API, or required to reach it. Never truncated.
     */
    public const PRESERVED_TABLES = [
        'parts',
        'part_categories',
        'part_category_part',
        'mobilesentrix_devices',
        'mobilesentrix_sync_logs',
        'mobilesentrix_api_settings',
    ];

    /**
     * Laravel infrastructure that must survive for the application to boot correctly.
     */
    public const PROTECTED_TABLES = [
        'migrations',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->option('pretend')) {
            $this->error('This permanently deletes application data. Re-run with --force (or --pretend to preview).');

            return self::FAILURE;
        }

        $keep = array_merge(self::PRESERVED_TABLES, self::PROTECTED_TABLES);
        $tables = collect($this->allTables())
            ->reject(fn (string $table): bool => in_array($table, $keep, true))
            ->values();

        $this->line('Preserving '.count($keep).' tables: '.implode(', ', $keep));
        $this->newLine();

        $cleared = 0;
        $rowsRemoved = 0;

        foreach ($tables as $table) {
            $count = DB::table($table)->count();

            if ($this->option('pretend')) {
                $this->line(sprintf('  would clear %-46s %8d rows', $table, $count));
                $rowsRemoved += $count;

                continue;
            }

            $rowsRemoved += $count;
            $cleared++;
        }

        if ($this->option('pretend')) {
            $this->newLine();
            $this->info(sprintf('Pretend: %d tables, %d rows would be removed.', $tables->count(), $rowsRemoved));

            return self::SUCCESS;
        }

        // Truncation order cannot satisfy every foreign key, so constraints are suspended for
        // the duration and restored in a finally block even if a truncate fails.
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
                $this->line('  cleared '.$table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->newLine();
        $this->info(sprintf('Cleared %d tables (%d rows). MobileSentrix data preserved.', $cleared, $rowsRemoved));

        foreach (self::PRESERVED_TABLES as $table) {
            $this->line(sprintf('  kept %-42s %8d rows', $table, DB::table($table)->count()));
        }

        return self::SUCCESS;
    }

    /**
     * Base tables in the configured schema only.
     *
     * Schema::getTables() can return tables from other schemas the database user can see, so the
     * schema is pinned explicitly here — truncating another application's tables would be
     * catastrophic and silent.
     */
    private function allTables(): array
    {
        $schema = DB::connection()->getDatabaseName();

        return collect(DB::select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [$schema, 'BASE TABLE'],
        ))
            ->pluck('name')
            ->all();
    }
}
