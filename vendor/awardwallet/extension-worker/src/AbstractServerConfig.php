<?php

namespace AwardWallet\ExtensionWorker;

use AwardWallet\Common\Parsing\Web\Proxy\ProxyManager;
use Psr\Log\LoggerInterface;

abstract class AbstractServerConfig implements ServerCheckConfigInterface
{

    protected LoggerInterface $logger;
    protected ProxyManager $proxyManager;

    public function __construct(LoggerInterface $logger, ProxyManager $proxyManager)
    {
        $this->logger = $logger;
        $this->proxyManager = $proxyManager;
    }

}