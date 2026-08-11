<?php

namespace App\Services\MobileSentrix;

use Illuminate\Support\Facades\DB;

/**
 * Mirrors App\Services\OrderNumberGenerator so procurement numbers follow the same
 * locked-sequence convention as Eclise customer order numbers, without sharing its series.
 */
class MobileSentrixOrderNumberGenerator
{
    public function next(): string
    {
        $year = (int) now()->year;
        $now = now();

        DB::table('mobilesentrix_order_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_sequence' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = DB::table('mobilesentrix_order_number_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->value('last_sequence') + 1;

        DB::table('mobilesentrix_order_number_sequences')
            ->where('year', $year)
            ->update([
                'last_sequence' => $sequence,
                'updated_at' => $now,
            ]);

        return sprintf('MS-ORD-%d-%07d', $year, $sequence);
    }
}
