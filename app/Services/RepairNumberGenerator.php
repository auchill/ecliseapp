<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RepairNumberGenerator
{
    public function next(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($year): string {
            $row = DB::table('repair_number_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::table('repair_number_sequences')->insert([
                    'year' => $year,
                    'last_sequence' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $row = DB::table('repair_number_sequences')
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();
            }

            $sequence = ((int) $row->last_sequence) + 1;

            DB::table('repair_number_sequences')->where('year', $year)->update([
                'last_sequence' => $sequence,
                'updated_at' => now(),
            ]);

            return sprintf('ECL-REP-%d-%07d', $year, $sequence);
        });
    }
}
