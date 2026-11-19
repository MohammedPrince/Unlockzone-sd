<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Every email the site sends uses this one branded template (navy header with the site logo, orange button):
 * a heading, a few lines of text, an optional table of details, an optional highlighted code and an optional button.
 */
class SiteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, string> $lines Paragraphs (plain text).
     * @param array<string, string> $details Label => value rows.
     * @param string|null $tone Colour of the strip under the header: success (green), danger (red), muted (grey); null = orange.
     */
    public function __construct(
        public string $mailSubject,
        public string $heading,
        public array $lines = [],
        public array $details = [],
        public ?string $code = null,
        public ?string $buttonText = null,
        public ?string $buttonUrl = null,
        public ?string $tone = null,
        public ?string $codeLabel = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site-html', text: 'emails.site-text');
    }
}
