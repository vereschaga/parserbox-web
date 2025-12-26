<?php

namespace AwardWallet\Common\Parsing\Web\Proxy\Provider;

use AwardWallet\Common\Parsing\Web\Proxy\Proxy;
use AwardWallet\Common\Parsing\Web\Proxy\ProxyProviderInterface;
use AwardWallet\Common\Parsing\Web\Proxy\ProxyProviderResponse;
use AwardWallet\Common\Parsing\Web\Proxy\ProxyRequestInterface;

class NetNut implements ProxyProviderInterface
{

    private string $username;
    private string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function getId(): string
    {
        return 'netnut';
    }

    public function supports(ProxyRequestInterface $request): bool
    {
        return $request instanceof NetNutRequest;
    }

    /**
     * @param NetNutRequest $request
     */
    public function get(ProxyRequestInterface $request, array $state): ProxyProviderResponse
    {
        if (!isset($state["sessionId"])) {
            $state["sessionId"] = random_int(1, 99999999);
        }

        $username = "{$this->username}-res-{$request->country}-sid-{$state["sessionId"]}";

        return new ProxyProviderResponse(
            new Proxy(
                'gw.ntnt.io',
                5959,
                $username,
                $this->password
            ),
            ["sessionId" => $state["sessionId"]]
        );
    }

    public function getProxyCheckUrl(): string
    {
        return "https://api.netnut.io/myIP.aspx";
    }

}