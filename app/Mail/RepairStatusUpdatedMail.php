<?php

namespace App\Mail;

use App\Models\RepairBooking;
use App\Models\RepairStatusUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RepairStatusUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RepairBooking $repair,
        public ?RepairStatusUpdate $statusUpdate = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Repair '.$this->repair->tracking_number.' status update',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.repairs.status-updated',
        );
    }
}
