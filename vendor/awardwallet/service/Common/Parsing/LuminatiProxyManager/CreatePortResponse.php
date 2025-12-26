<?php

namespace AwardWallet\Common\Parsing\LuminatiProxyManager;

class CreatePortResponse
{

    private int $portNumber;
    private ?int $cachePortNumber;

    public function __construct(int $portNumber, ?int $cachePortNumber)
    {

        $this->portNumber = $portNumber;
        $this->cachePortNumber = $cachePortNumber;
    }

    public function getPortNumber(): int
    {
        return $this->portNumber;
    }

    public function getCachePortNumber(): ?int
    {
        return $this->cachePortNumber;
    }

}