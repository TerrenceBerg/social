<?php
namespace Tuna976\Social\Contracts;

use Carbon\Carbon;

interface TokenStorageInterface
{
    public function getAccessToken(): ?string;

    public function getRefreshToken(): ?string;

    public function getExpiresAt(): ?Carbon;

    public function storeTokens(array $tokenData): void;

    public function setProvider(string $provider): void;

}
