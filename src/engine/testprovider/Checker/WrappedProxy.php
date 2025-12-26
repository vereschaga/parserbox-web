<?php

namespace AwardWallet\Engine\testprovider\Checker;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\testprovider\Success;
use AwardWallet\Common\Parsing\WrappedProxyClient;

class WrappedProxy extends Success
{
    use ProxyList;

    public function Parse()
    {
        $this->ArchiveLogs = true;

        $this->setProxyBrightData();
        $this->http->GetURL('http://lumtest.com/myip.json');
        $responseBody = $this->http->Response['body'];

        $wrappedProxy = $this->services->get(WrappedProxyClient::class);
        $wrappedProxyPort = $wrappedProxy->createPort($this->http->getProxyParams());

        $this->http->SetProxy($wrappedProxyPort['proxyHost'] . ':' . $wrappedProxyPort['proxyPort']);
        $this->http->setProxyAuth($wrappedProxyPort['proxyLogin'], $wrappedProxyPort['proxyPassword']);

        $this->http->GetURL('http://lumtest.com/myip.json');

        if ($responseBody == $this->http->Response['body']){
            $this->SetBalance(1);
        }
    }
}
