<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Every email the site sends uses this one template: a heading, a few lines of text,
 * an optional table of details, an optional highlighted code and an optional button.
 */
class SiteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, string> $lines Paragraphs (plain text).
     * @param array<string, string> $details Label => value rows.
     */
    public function __construct(
        public string $mailSubject,
        public string $heading,
        public array $lines = [],
        public array $details = [],
        public ?string $code = null,
        public ?string $buttonText = null,
        public ?string $buttonUrl = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.site');
    }
}
