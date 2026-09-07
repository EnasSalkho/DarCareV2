<?php

namespace App\Mail;

use App\Modules\Providers\Models\Provider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProviderApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Provider $provider
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تم قبول حسابك في DarCare',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.provider-approved',
            with: ['name' => $this->provider->name],
        );
    }
}
