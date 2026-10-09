<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $code  the 4-digit code
     * @param  string  $purpose  what the code confirms, e.g. "reset your password"
     */
    public function __construct(public string $code, public string $purpose)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your verification code');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.verification-code');
    }
}
