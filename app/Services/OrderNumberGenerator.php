<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrderNumberGenerator
{
    public function next(): string
    {
        $year = (int) now()->year;
        $now = now();

        DB::table('order_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_sequence' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = DB::table('order_number_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->value('last_sequence') + 1;

        DB::table('order_number_sequences')
            ->where('year', $year)
            ->update([
                'last_sequence' => $sequence,
                'updated_at' => $now,
            ]);

        return sprintf('ECL-ORD-%d-%07d', $year, $sequence);
    }
}
