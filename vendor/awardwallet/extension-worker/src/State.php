<?php

namespace AwardWallet\ExtensionWorker;

class State
{

    public array $parserState = [];
    public bool $keepBrowserState = true;
    public bool $keepBrowserSession = false;
    public bool $browserSessionRestored = false;
    public ?string $tabId = null;
    public ?int $frameId = null;
    public array $browserState = [];
    public array $proxyProviderState = [];
    public ?string $question = null;

}