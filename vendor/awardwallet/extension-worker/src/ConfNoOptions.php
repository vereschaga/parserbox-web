<?php

namespace AwardWallet\ExtensionWorker;

class ConfNoOptions
{

    private bool $isMobile;

    public function __construct(bool $isMobile)
    {
        $this->isMobile = $isMobile;
    }

    public function isMobile(): bool
    {
        return $this->isMobile;
    }

}