<?php

namespace AwardWallet\Common\Parsing\Web\Proxy\Provider;

use AwardWallet\Common\Parsing\Web\Proxy\ProxyRequestInterface;

class MountRequest implements ProxyRequestInterface
{

    public const CITY_WASHINGTON = 'DC';
    public const CITY_SEATTLE = 'WA';

    public ?string $city = null;

    public function __construct(?string $city = null)
    {
        $this->city = $city;
    }

}