<?php

namespace AwardWallet\ExtensionWorker;

use Psr\Log\LoggerInterface;

class ServerCheckOptionsFactory
{

    private LoggerInterface $logger;
    private ServerConfigFactory $serverConfigFactory;

    public function __construct(LoggerInterface $logger, ServerConfigFactory $serverConfigFactory)
    {
        $this->logger = $logger;
        $this->serverConfigFactory = $serverConfigFactory;
    }

    public function getServerCheckOptions(string $providerCode, AccountOptions $accountOptions, array &$proxyProviderState) : ?ServerCheckOptions
    {
        $config = $this->serverConfigFactory->getConfig($providerCode, $proxyProviderState);
        if ($config === null) {
            $this->logger->info("no server config for provider {$providerCode}, will not use v3 server check");

            return null;
        }

        $seleniumRequest = new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_CHROME_EXTENSION, \SeleniumFinderRequest::CHROME_EXTENSION_104);
        $seleniumOptions = new \SeleniumOptions();
        if (!$config->configureServerCheck($accountOptions, $seleniumRequest, $seleniumOptions)) {
            $this->logger->info("configureServerCheck returned false, will not use v3 server check");

            return null;
        }

        if ($seleniumRequest->getBrowser() !== \SeleniumFinderRequest::BROWSER_CHROME_EXTENSION || $seleniumRequest->getVersion() !== \SeleniumFinderRequest::CHROME_EXTENSION_104)  {
            throw new \Exception("Sorry, only chrome-extension:104 browser is supported for v3 server check now");
        }

        if ($seleniumRequest->getOs() === \SeleniumFinderRequest::OS_MAC) {
            throw new \Exception("Sorry, no macs supported for v3 server check now");
        }

        $this->logger->info("server check configured, will use v3 server check");

        return new ServerCheckOptions($seleniumRequest, $seleniumOptions);
    }
}