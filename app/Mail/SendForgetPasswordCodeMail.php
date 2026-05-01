<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;

use Illuminate\Mail\Mailable;

use Illuminate\Queue\SerializesModels;

class SendForgetPasswordCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    private $code;
    private $email;
    /**
     * Create a new message instance.
     */
    public function __construct($code, $email)
    {
        $this->code = $code;
        $this->email = $email;
    }
    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Verification Code')
            ->view('emails.forget_password_code')
            ->with([
                'code' => $this->code,
                'user' => ['email' => $this->email],
            ]);
    }
}
