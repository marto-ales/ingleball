<?php

namespace App\Mail;

use App\Models\Group;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewRegistration extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Group $group) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nuevo jugador en '.$this->group->name.': '.$this->user->name);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('mails.new-registration', ['user' => $this->user, 'group' => $this->group])->render(),
        );
    }
}
