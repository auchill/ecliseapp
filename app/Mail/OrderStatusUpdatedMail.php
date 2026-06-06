<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderStatusUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?OrderStatusUpdate $statusUpdate = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order '.$this->order->order_number.' status update',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.status-updated',
        );
    }
}
