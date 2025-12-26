<?php

namespace AwardWallet\ExtensionWorker\Commands;

class SetCookieRequest {

    public int $tabId;
    public int $frameId;
    public string $name;
    public string $value;
    public ?string $domain;
    public ?string $path;
    public ?string $sameSite;
    public ?bool $secure;
    public ?int $maxAge;

    public function __construct(int $tabId, int $frameId, string $name, string $value, ?string $domain, ?string $path, ?string $sameSite, ?bool $secure, ?int $maxAge)
    {
        $this->tabId = $tabId;
        $this->frameId = $frameId;
        $this->name = $name;
        $this->value = $value;
        $this->domain = $domain;
        $this->path = $path;
        $this->sameSite = $sameSite;
        $this->secure = $secure;
        $this->maxAge = $maxAge;
    }

}
