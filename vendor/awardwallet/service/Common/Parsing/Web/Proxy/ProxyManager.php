<?php

namespace AwardWallet\Common\Parsing\Web\Proxy;

use Psr\Log\LoggerInterface;

class ProxyManager
{

    private LoggerInterface $logger;
    private array $state;
    /**
     * @var ProxyProviderInterface[]
     */
    private iterable $proxyProviders;
    private \HttpDriverInterface $httpDriver;

    public function __construct(LoggerInterface $logger, array &$state, iterable $proxyProviders, \HttpDriverInterface $httpDriver)
    {
        $this->logger = $logger;
        $this->state = $state;
        $this->proxyProviders = $proxyProviders;
        $this->httpDriver = $httpDriver;
    }

    public function get(ProxyRequestInterface $proxyRequest) : Proxy
    {
        $provider = $this->getProviderForRequest($proxyRequest);
        $try = 0;
        while ($try < 5) {
            $response = $provider->get($proxyRequest, $this->state[$provider->getId()] ?? []);
            if ($this->validateProxy($response->getProxy(), $provider->getProxyCheckUrl())) {
                $this->state[$provider->getId()] = $response->getState();
                $this->logger->info("using proxy {$provider->getId()}: {$response->getProxy()->username}@{$response->getProxy()->host}:{$response->getProxy()->port}");

                return $response->getProxy();
            }

            // will discard state on second attempt and later
            $this->state[$provider->getId()] = [];

            $try++;
        }

        throw new \Exception("Could not get live proxy for " . $provider->getId());
    }

    private function getProviderForRequest(ProxyRequestInterface $proxyRequest): ProxyProviderInterface
    {
        $provider = null;
        foreach ($this->proxyProviders as $provider) {
            if ($provider->supports($proxyRequest)) {
                break;
            }
        }

        if ($provider === null) {
            throw new \Exception("Unknown proxy provider for request: " . get_class($proxyRequest));
        }

        return $provider;
    }

    private function validateProxy(Proxy $proxy, string $proxyCheckUrl) : bool
    {
        $request = new \HttpDriverRequest($proxyCheckUrl);
        $request->proxyAddress = $proxy->host;
        $request->proxyPort = $proxy->port;
        $request->proxyLogin = $proxy->username;
        $request->proxyPassword = $proxy->password;
        $request->timeout = 5;

        $response = $this->httpDriver->request($request);
        $success = $response->httpCode == 200;

        if (!$success) {
            $this->logger->warning("failed to get $proxyCheckUrl with proxy {$proxy->username}@{$proxy->host}:{$proxy->port}: {$response->httpCode}, " . substr($response->errorMessage, 200));
        }

        return $success;
    }

}