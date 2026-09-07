<?php

namespace App\Mail;

use App\Modules\Providers\Models\Provider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProviderRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Provider $provider
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'بخصوص طلب انضمامك إلى DarCare',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.provider-rejected',
            with: [
                'name' => $this->provider->name,
                'reason' => $this->provider->rejection_reason,
            ],
        );
    }
}
