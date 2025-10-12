<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Form extends Mailable
{
    use Queueable;
    use SerializesModels;

    private $type;
    public $textView = null;

    /**
     * Create a new message instance.
     */
    public function __construct(public array $formData, string $subject, string $view, string $type, ?string $textView = null)
    {
        $this->subject = $subject;
        $this->view = $view;
        $this->type = $type;
        $this->textView = $textView;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        if ($this->type === 'text') {
            return new Content(text: $this->view);
        }

        // Provide HTML view and, if configured, a plain-text alternative for better client previews
        return new Content(
            view: $this->view,
            text: $this->textView
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
