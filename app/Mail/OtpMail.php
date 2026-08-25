<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $mailSubject;
    public $mailMessage;

    /**
     * Create a new message instance.
     */
    public function __construct($otp, $mailSubject, $mailMessage)
    {
        $this->otp = $otp;
        $this->mailSubject = $mailSubject;
        $this->mailMessage = $mailMessage;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Verification OTP',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {



        return new Content(
            view: 'emails.otp',
            with: [
                'otp' => $this->otp,
                'subject' => $this->mailSubject,
                'body' => $this->mailMessage,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
