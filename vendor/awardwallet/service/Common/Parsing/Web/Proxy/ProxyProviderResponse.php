<?php

namespace AwardWallet\Common\Parsing\Web\Proxy;

class ProxyProviderResponse
{

    private Proxy $proxy;
    private array $state;

    public function __construct(Proxy $proxy, array $state)
    {
        $this->proxy = $proxy;
        $this->state = $state;
    }

    public function getProxy(): Proxy
    {
        return $this->proxy;
    }

    public function getState(): array
    {
        return $this->state;
    }

}