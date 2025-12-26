<?php

namespace AwardWallet\ExtensionWorker;

class ProviderInfo
{

    private string $displayName;
    private string $shortName;

    public function __construct(string $displayName, string $shortName)
    {
        $this->displayName = $displayName;
        $this->shortName = $shortName;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

}