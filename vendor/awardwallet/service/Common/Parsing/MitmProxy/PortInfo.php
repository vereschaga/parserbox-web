<?php

namespace AwardWallet\Common\Parsing\MitmProxy;

use JMS\Serializer\Annotation as Serializer;

class PortInfo
{

    /**
     * how much traffic was routed to proxies
     * @var int[] - array of proxy stats ,like ["localhost:3128" => 1200, "some.proxy.com:8080" => 300]
     * @Serializer\Type("array<string, int>")
     */
    private array $proxyStats;
    /**
     * @Serializer\Type("int")
     */
    private int $port;

    public function getProxyStats(): array
    {
        return $this->proxyStats;
    }

    public function getPort(): int
    {
        return $this->port;
    }

}