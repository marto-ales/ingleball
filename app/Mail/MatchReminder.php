<?php

namespace App\Mail;

use App\Models\Partido;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MatchReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Partido $match)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recordatorio: ' . $this->match->title);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('mails.match-reminder', ['match' => $this->match])->render(),
        );
    }
}
