<?php

namespace AwardWallet\Common\Parsing\Web\Proxy\Provider;

use AwardWallet\Common\Parsing\Web\Proxy\ProxyRequestInterface;

class NetNutRequest implements ProxyRequestInterface
{

    public const COUNTRY_US = 'us';

    public string $country;

    public function __construct(string $country = self::COUNTRY_US)
    {
        $this->country = $country;
    }

}