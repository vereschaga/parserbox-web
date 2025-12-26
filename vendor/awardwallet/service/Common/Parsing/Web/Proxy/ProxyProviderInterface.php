<?php

namespace AwardWallet\Common\Parsing\Web\Proxy;

interface ProxyProviderInterface
{

    public function getId() : string;
    public function supports(ProxyRequestInterface $request) : bool;
    public function get(ProxyRequestInterface $request, array $state) : ProxyProviderResponse;
    public function getProxyCheckUrl() : string;

}