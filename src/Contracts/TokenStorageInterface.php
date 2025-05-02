<?php
namespace Tuna976\Social\Contracts;

interface TokenStorageInterface
{
    public function getAccessToken(): ?string;

    public function getRefreshToken(): ?string;

    public function getExpiresAt(): ?int;

    public function storeTokens(array $tokenData): void;

    public function setProvider(string $provider): void;

}
