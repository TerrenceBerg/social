<?php

namespace Tuna976\Social\Contracts;

interface TwitterServiceInterface
{
    public function authenticate();

    public function postMessage(string $message, array $options = []);

    public function postMedia(string $message, string $mediaPath);

    public function fetchTimeline();
}
