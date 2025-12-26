<?php

namespace AwardWallet\Common\Parsing\Web\Proxy\Provider;

use AwardWallet\Common\Parsing\Web\Proxy\Proxy;
use AwardWallet\Common\Parsing\Web\Proxy\ProxyProviderInterface;
use AwardWallet\Common\Parsing\Web\Proxy\ProxyProviderResponse;
use AwardWallet\Common\Parsing\Web\Proxy\ProxyRequestInterface;

class Mount implements ProxyProviderInterface
{

    private const CITY_PARAMS = [
        MountRequest::CITY_WASHINGTON => [
            'proxy_ip'         => '173.255.233.234',
            'port'             => '8260',
            'subscription_key' => '6570a17a6b60e2da57db90ca',
        ],
        MountRequest::CITY_SEATTLE => [
            'proxy_ip'         => '45.79.74.226',
            'port'             => '8744',
            'subscription_key' => '65ef11ec242597900580fd0d',
        ],
    ];

    private string $username;
    private string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function getId(): string
    {
        return 'mount';
    }

    public function supports(ProxyRequestInterface $request): bool
    {
        return $request instanceof MountRequest;
    }

    /**
     * @param MountRequest $request
     */
    public function get(ProxyRequestInterface $request, array $state): ProxyProviderResponse
    {
        $city = $request->city;

        if ($city !== null && !in_array($city, array_keys(self::CITY_PARAMS))) {
            throw new \InvalidArgumentException("Unsupported city: {$request->city}, allowed only: ". join(", ", array_keys(self::CITY_PARAMS)));
        }

        if ($city === null) {
            $city = array_rand(self::CITY_PARAMS);
        }

        return new ProxyProviderResponse(
            new Proxy(
                self::CITY_PARAMS[$city]['proxy_ip'],
                self::CITY_PARAMS[$city]['port'],
                $this->username,
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