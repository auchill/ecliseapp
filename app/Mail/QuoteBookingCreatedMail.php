<?php

namespace App\Mail;

use App\Models\Repair;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteBookingCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Repair $repair) {}

    public function build(): self
    {
        return $this
            ->subject('Your Eclise repair number '.$this->repair->repair_number)
            ->view('emails.quotes.booking-created')
            ->with([
                'booking' => $this->repair,
                'repair' => $this->repair,
            ]);
    }
}
