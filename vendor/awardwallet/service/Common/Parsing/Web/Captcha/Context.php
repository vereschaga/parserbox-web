<?php

namespace AwardWallet\Common\Parsing\Web\Captcha;

class Context
{

    private string $partner;
    private string $provider;
    private string $accountId;
    private string $userAgent;

    public function __construct(string $partner, string $provider, string $accountId, string $userAgent)
    {

        $this->partner = $partner;
        $this->provider = $provider;
        $this->accountId = $accountId;
        $this->userAgent = $userAgent;
    }

    public function getPartner(): string
    {
        return $this->partner;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

}