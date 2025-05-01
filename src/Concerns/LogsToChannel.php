<?php

namespace Tuna976\Social\Concerns;

use Illuminate\Support\Facades\Log;

trait LogsToChannel
{
    protected function logChannel()
    {
        $channel = config('social.log_channel', config('logging.default'));
        return Log::channel($channel);
    }

    protected function logInfo(string $message): void
    {
        $this->logChannel()->info($message);
    }

    protected function logError(string $message): void
    {
        $this->logChannel()->error($message);
    }

    protected function logWarning(string $message): void
    {
        $this->logChannel()->warning($message);
    }

    protected function logDebug(string $message): void
    {
        $this->logChannel()->debug($message);
    }
}
