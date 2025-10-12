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

    /**
     * Create a new message instance.
     */
    public function __construct(public array $formData, string $subject, string $view, string $type)
    {
        $this->subject = $subject;
        $this->view = $view;
        $this->type = $type;
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
        if ($this->type == 'text') {
            $content = new Content(text: $this->view);
        } else {
            $content = new Content(view: $this->view);
        }

        return $content;
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
