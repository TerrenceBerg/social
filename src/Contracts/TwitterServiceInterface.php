<?php

namespace Tuna976\Contracts;

interface TwitterServiceInterface
{
    /**
     * Authenticates the user with Twitter.
     *
     * @return void
     */
    public function authenticate(): void;

    /**
     * Posts a message to Twitter.
     *
     * @param string $message
     * @param array $options Additional options, e.g., hashtags, mentions, etc.
     * @return mixed
     */
    public function postMessage(string $message, array $options = []): mixed;

    /**
     * Posts media (image/video) to Twitter with an accompanying message.
     *
     * @param string $message
     * @param string $mediaPath Path to the media file
     * @return mixed
     */
    public function postMedia(string $message, string $mediaPath): mixed;

    /**
     * Fetches the user's Twitter timeline.
     *
     * @return mixed
     */
    public function fetchTimeline(): mixed;
}
