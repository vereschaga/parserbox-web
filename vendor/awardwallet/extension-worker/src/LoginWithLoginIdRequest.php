<?php

namespace AwardWallet\ExtensionWorker;

class LoginWithLoginIdRequest
{
    private Credentials $credentials;
    private ?bool $activeTab;
    private string $loginId;
    private FileLogger $fileLogger;
    private ?string $affiliateLink;

    public function __construct(Credentials $credentials, ?bool $activeTab, string $loginId, FileLogger $fileLogger, ?string $affiliateLink)
    {
        $this->credentials = $credentials;
        $this->activeTab = $activeTab;
        $this->loginId = $loginId;
        $this->fileLogger = $fileLogger;
        $this->affiliateLink = $affiliateLink;
    }

    public function getCredentials(): Credentials
    {
        return $this->credentials;
    }

    public function getActiveTab(): ?bool
    {
        return $this->activeTab;
    }

    public function getLoginId(): string
    {
        return $this->loginId;
    }

    public function getFileLogger(): FileLogger
    {
        return $this->fileLogger;
    }

    public function getAffiliateLink(): ?string
    {
        return $this->affiliateLink;
    }

}