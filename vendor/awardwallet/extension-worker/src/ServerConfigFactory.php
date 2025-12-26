<?php

namespace AwardWallet\ExtensionWorker;

use AwardWallet\Common\Parsing\Web\Proxy\ProxyManager;
use Psr\Log\LoggerInterface;

class ServerConfigFactory
{

    private LoggerInterface $logger;
    private iterable $proxyProviders;
    private \HttpDriverInterface $httpDriver;

    public function __construct(LoggerInterface $logger, iterable $proxyProviders, \HttpDriverInterface $httpDriver)
    {
        $this->logger = $logger;
        $this->proxyProviders = $proxyProviders;
        $this->httpDriver = $httpDriver;
    }

    public function getConfig(string $providerCode, array &$proxyProviderState) : ?ServerCheckConfigInterface
    {
        $className = 'AwardWallet\\Engine\\' . $providerCode . '\\' . ucfirst($providerCode) . 'ExtensionServerConfig';
        if (!class_exists($className)) {
            $this->logger->info("server config class $className does not exist, will not use v3 server check");

            return null;
        }

        $proxyManager = new ProxyManager($this->logger, $proxyProviderState, $this->proxyProviders, $this->httpDriver);

        $result = new $className($this->logger, $proxyManager);
        if (!$result instanceof ServerCheckConfigInterface) {
            $this->logger->warning("server config class $className does not support ServerCheckConfigInterface");

            return null;
        }

        $this->logger->info("server config class $className found");

        return $result;
    }

}