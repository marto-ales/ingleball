<?php

namespace App\Services;

use App\Mail\MatchReminder;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ReminderService
{
    public function sendEmail(User $user, Partido $match): bool
    {
        if ($user->email === null || $user->email === '') {
            return false;
        }

        Mail::to($user->email)->send(new MatchReminder($match));

        return true;
    }
}
