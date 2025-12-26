<?php

namespace AwardWallet\Common\Parsing\Web\Proxy\Provider;

use AwardWallet\Common\Parsing\Web\Proxy\Proxy;
use AwardWallet\Common\Parsing\Web\Proxy\ProxyProviderInterface;
use AwardWallet\Common\Parsing\Web\Proxy\ProxyProviderResponse;
use AwardWallet\Common\Parsing\Web\Proxy\ProxyRequestInterface;

class GoProxies implements ProxyProviderInterface
{

    private const REGIONS = [
        // Europe countries
        'proxy-europe.goproxies.com' => [
            'gb', 'no', 'fi', 'ee', 'dk', 'cz', 'fr', 'gr', 'it', 'es', 'de', 'pt', 'be', 'bl', 'ru',
        ],
        // Asia and Oceania countries
        'proxy-asia.goproxies.com' => [
            'au', 'jp', 'cn', 'kr', 'hk', 'id', 'th',
        ],
        // North America and South America countries
        'proxy-america.goproxies.com' => [
            'us', 'ca', 'br', 'cl', 'mx',
        ],
    ];

    private string $username;
    private string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @param GoProxiesRequest $request
     */
    public function get(ProxyRequestInterface $request, array $state): ProxyProviderResponse
    {
        $host = $this->getHost($request->country);

        if (!isset($state["sessionId"])) {
            $state["sessionId"] = random_int(1, 99999999);
        }

        $username = "customer-{$this->username}-country-{$request->country}-sessionid-{$state["sessionId"]}";

        return new ProxyProviderResponse(
            new Proxy(
                $host,
                10000,
                $username,
                $this->password
            ),
            ["sessionId" => $state["sessionId"]]
        );
    }

    private function getHost(string $country): string
    {
        $host = null;

        foreach (self::REGIONS as $regionHost => $list) {
            if (in_array($country, $list)) {
                $host = $regionHost;

                break;
            }
        }

        if ($host === null) {
            throw new \Exception("Invalid country {$country} for GoProxies proxy. Allowed: ". join(", ", array_merge(...array_values(self::REGIONS))));
        }

        return $host;
    }

    public function supports(ProxyRequestInterface $request): bool
    {
        return $request instanceof GoProxiesRequest;
    }

    public function getProxyCheckUrl(): string
    {
        return "https://ip.goproxies.com";
    }

    public function getId(): string
    {
        return 'goproxies';
    }
}