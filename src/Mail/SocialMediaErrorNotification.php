<?php

namespace Tuna976\Social\Mail;

use Illuminate\Mail\Mailable;

class SocialMediaErrorNotification extends Mailable
{
    public string $messageContent;

    public function __construct(string $messageContent)
    {
        $this->messageContent = $messageContent;
    }

    public function build()
    {
        return $this->subject('Social Media Error Notification')
            ->view('social::emails.error')
            ->with(['messageContent' => $this->messageContent]);
    }
}
?>
