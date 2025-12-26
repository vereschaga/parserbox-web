<?php

namespace AwardWallet\ExtensionWorker;

class ParserContext
{

    private ProviderInfo $providerInfo;
    private bool $isMobile;
    private bool $isServerCheck;
    private bool $isBackground;
    private bool $mailboxConnected;

    public function __construct(ProviderInfo $providerInfo, bool $isMobile, bool $isServerCheck, bool $isBackground, bool $mailboxConnected)
    {
        $this->providerInfo = $providerInfo;
        $this->isMobile = $isMobile;
        $this->isServerCheck = $isServerCheck;
        $this->isBackground = $isBackground;
        $this->mailboxConnected = $mailboxConnected;
    }

    public function getProviderInfo(): ProviderInfo
    {
        return $this->providerInfo;
    }

    public function isMobile(): bool
    {
        return $this->isMobile;
    }

    public function isServerCheck(): bool
    {
        return $this->isServerCheck;
    }

    public function isBackground(): bool
    {
        return $this->isBackground;
    }

    public function isMailboxConnected(): bool
    {
        return $this->mailboxConnected;
    }

}