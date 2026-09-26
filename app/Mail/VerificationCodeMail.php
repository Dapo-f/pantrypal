<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code)
    {
    }

    public function build()
    {
        return $this->subject('Verify Your PantryPal Account')
            ->view('emails.verification-code')
            ->with(['user' => $this->user, 'code' => $this->code]);
    }
}