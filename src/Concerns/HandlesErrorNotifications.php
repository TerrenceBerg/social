<?php

namespace Tuna976\Social\Concerns;

use Illuminate\Support\Facades\Mail;
use Tuna976\Social\Mail\SocialMediaErrorNotification;
use Illuminate\Support\Facades\Log;

trait HandlesErrorNotifications
{
    protected function notifyError(string $message): void
    {
        if (method_exists($this, 'logError')) {
            $this->logError($message);
        } else {
            Log::error($message);
        }

        if (filter_var(config('social.log_emails.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            $to = config('social.log_emails.social_log_email_address');
            if ($to) {
                Mail::to($to)->send(new SocialMediaErrorNotification($message));
            }
        }
    }
}
