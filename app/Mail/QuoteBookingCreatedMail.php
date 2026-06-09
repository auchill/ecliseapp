<?php

namespace App\Mail;

use App\Models\RepairBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteBookingCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RepairBooking $booking) {}

    public function build(): self
    {
        return $this
            ->subject('Your Eclise repair tracking number '.$this->booking->tracking_number)
            ->view('emails.quotes.booking-created');
    }
}
