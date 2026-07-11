<?php

namespace App\Models;

/**
 * Backward-compatible alias while routes, mailables, and older references move
 * from RepairBooking terminology to Repair.
 */
class RepairBooking extends Repair
{
    public function getMorphClass(): string
    {
        return Repair::class;
    }
}
