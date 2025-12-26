<?php

namespace AwardWallet\ExtensionWorker;

class ParserRunnerLoginWithConfNoResult
{

    public Tab $tab;
    public ?string $errorMessage;
    public ?string $debugInfo = null;

    public function __construct(?string $errorMessage, Tab $tab, ?string $debugInfo = null)
    {
        $this->errorMessage = $errorMessage;
        $this->tab = $tab;
        $this->debugInfo = $debugInfo;
    }

}