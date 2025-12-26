<?php

namespace AwardWallet\Engine\testprovider\Checker;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\testprovider\Success;
use AwardWallet\Common\Parsing\LuminatiProxyManager as LMP;

class LuminatiProxyManager extends Success
{
    use ProxyList;

    public function Parse()
    {
        $this->ArchiveLogs = true;

        $this->http->GetURL('http://lumtest.com/myip.json');
        $responseBody = $this->http->Response['body'];

        $lpm = $this->services->get(LMP\Client::class);
        $externalProxy = $this->getProxyHost('bd');

        $port = (new LMP\Port)->setExternalProxy([$externalProxy]);

        $portNumber = $lpm->createProxyPort($port);
        $this->logger->info("Port number: {$portNumber}");

        $this->setLpmProxy(
            $lpm->getInternalIp() . ':' . $portNumber,
            "http://lumtest.com/myip.json"
        );

        $this->http->GetURL('http://lumtest.com/myip.json');

        if ($responseBody !== $this->http->Response['body']) {
            $this->SetBalance(1);
        }
    }
}
