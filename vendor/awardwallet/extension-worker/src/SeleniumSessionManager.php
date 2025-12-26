<?php

namespace AwardWallet\ExtensionWorker;

use AwardWallet\Common\Selenium\SeleniumDriverFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

class SeleniumSessionManager
{

    private SeleniumDriverFactory $seleniumDriverFactory;
    private LoggerInterface $logger;
    private SessionManager $sessionManager;
    private string $centrifugeUrl;
    private string $responseEndpoint;
    private \Memcached $memcached;
    private string $saveLoginIdEndpoint;

    public function __construct(
        SeleniumDriverFactory $seleniumDriverFactory,
        LoggerInterface $logger,
        SessionManager $sessionManager,
        string $centrifugeUrl,
        \Memcached $memcached,
        RouterInterface $router
    )
    {
        $this->seleniumDriverFactory = $seleniumDriverFactory;
        $this->logger = $logger;
        $this->sessionManager = $sessionManager;
        $this->centrifugeUrl = $centrifugeUrl;
        $this->responseEndpoint = $router->generate('puppeteer_extension_response', [], RouterInterface::ABSOLUTE_URL);
        $this->saveLoginIdEndpoint = $router->generate('extension_save_login_id', [], RouterInterface::ABSOLUTE_URL);
        $this->memcached = $memcached;
    }

    public function start(ServerCheckOptions $options, array $state, ?string $extensionSessionId) : ExtensionSeleniumSession
    {
        $driver = $this->seleniumDriverFactory->getDriver($options->request, $options->options, $this->logger);
        $driver->setState($state);

        // will try to restore session, if it fails - will start a new one
        $driver->onStart = function (\SeleniumOptions $options) use (&$extensionSessionId) {
            $session = $this->sessionManager->create();
            $options->extensionSessionId = $session->getSessionId();
            $options->extensionToken = $session->getCentrifugoJwtToken();
            $options->extensionCentrifugeEndpoint = $this->centrifugeUrl;
            $options->extensionResponseEndpoint = $this->responseEndpoint;
            $options->extensionSaveLoginIdEndpoint = $this->saveLoginIdEndpoint;
            $this->memcached->set($this->getSessionCacheKey($session->getSessionId()), '1', 3600);
            $extensionSessionId = $session->getSessionId();
            $this->logger->info("created selenium extension session: $extensionSessionId");
        };

        $driver->start(null, $options->options->proxyUser, $options->options->proxyPassword);

        $this->logger->info("returning selenium extension session: $extensionSessionId");

        return new ExtensionSeleniumSession($driver, $extensionSessionId);
    }

    public function sessionExists(string $sessionId) : bool
    {
        return $this->memcached->get($this->getSessionCacheKey($sessionId)) === '1';
    }

    private function getSessionCacheKey(string $sessionId) : string
    {
        return 'sel_sess_' . $sessionId;
    }

    public function saveLoginId(string $sessionId, string $loginId, string $login)
    {
        $this->logger->info("saved login id $loginId, login $login for session $sessionId");
        $this->memcached->set($this->getLoginIdKey($sessionId), ["loginId" => $loginId, "login" => $login], 3600);
    }

    private function getLoginIdKey(string $sessionId) : string
    {
        return 'sel_login_id_' . $sessionId;
    }

}