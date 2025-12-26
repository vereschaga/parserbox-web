<?php

namespace AwardWallet\Common\Parsing\Web;

use AwardWallet\Common\Document\GoProxiesSubuser;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;

class GoProxiesSubuserManager
{

    private LoggerInterface $logger;
    private \HttpDriverInterface $httpDriver;
    private string $goproxiesRootLogin;
    private string $goproxiesRootPassword;
    private \Memcached $memcached;
    private DocumentManager $documentManager;

    public function __construct(
        LoggerInterface $logger,
        \HttpDriverInterface $httpDriver,
        string $goproxiesRootLogin,
        string $goproxiesRootPassword,
        \Memcached $memcached,
        DocumentManager $documentManager
    )
    {
        $this->logger = $logger;
        $this->httpDriver = $httpDriver;
        $this->goproxiesRootLogin = $goproxiesRootLogin;
        $this->goproxiesRootPassword = $goproxiesRootPassword;
        $this->memcached = $memcached;
        $this->documentManager = $documentManager;
    }

    public function getSubuserPassword(string $userName) : ?string
    {
        if ($this->goproxiesRootLogin === '') {
            $this->logger->info("GoProxiesSubuserManager not configured, will not use per-provider logins");

            return null;
        }

        $userPasswordKey = "goproxies_pass_" . $userName;
        $userPassword = $this->memcached->get($userPasswordKey);
        if ($userPassword !== false) {
            $this->logger->info("goproxies user $userName already exists in cache");

            return $userPassword;
        }

        /** @var GoProxiesSubuser $user */
        $user = $this->documentManager->find(GoProxiesSubuser::class, $userName);
        if ($user !== null) {
            $this->memcached->set($userPasswordKey, $user->getPassword(), 300);

            return $user->getPassword();
        }

        $lockKey = "goproxies_lock_" . $userName;
        if (!$this->memcached->add($lockKey, true, 60)) {
            $this->logger->info("goproxies: failed to lock $lockKey");

            return null;
        }

        $loginToken = $this->getToken();
        $userPassword = $this->createUser($loginToken, $userName);
        if ($userPassword === null) {
            return null;
        }

        $user = new GoProxiesSubuser($userName, $userPassword);
        $this->documentManager->persist($user);
        try {
            $this->documentManager->flush();
        }
        catch (\Exception $e) {
            $this->logger->warning("failed to save goproxies user $userName: " . $e->getMessage());

            return null;
        }

        $this->memcached->set($userPasswordKey, $userPassword, 300);
        $this->memcached->delete($lockKey);

        return $userPassword;
    }

    private function getToken() : ?string
    {
        $loginTokenKey = "goproxies_login_token_" . $this->goproxiesRootLogin;
        $loginToken = $this->memcached->get($loginTokenKey);
        if ($loginToken !== false) {
            return $loginToken;
        }

        $response = $this->httpDriver->request(new \HttpDriverRequest('https://api.goproxies.com/api/v1/login', 'POST', json_encode([
            'username' => $this->goproxiesRootLogin,
            'password' => $this->goproxiesRootPassword,
        ]), ['Content-Type: application/json'], 10));

        if ($response->httpCode < 200 || $response->httpCode > 299) {
            $this->logger->warning("failed to get login token for go proxies: {$response->httpCode} {$response->body}");

            return null;
        }

        $loginToken = @json_decode($response->body, true)['token'];
        if (!is_string($loginToken) || strlen($loginToken) < 10) {
            $this->logger->warning("failed to decode login token for go proxies: {$response->body}");
            return null;
        }

        $this->logger->info("create login token for goproxies");
        $this->memcached->set($loginTokenKey, $loginToken, 300);

        return $loginToken;
    }

    private function createUser(string $loginToken, string $userName) : ?string
    {
        $this->logger->info("creating goproxies subuser $userName");
        $response = $this->httpDriver->request(new \HttpDriverRequest('https://api.goproxies.com/api/v1/reseller/subusers', 'POST', json_encode([
            'username' => $userName,
            'enabled' => true,
        ]), ['Authorization' => 'Bearer ' . $loginToken, 'Content-Type: application/json'], 10));

        if ($response->httpCode < 200 || $response->httpCode > 299) {
            $this->logger->warning("failed to create user $userName for go proxies: {$response->httpCode} {$response->body}");

            return null;
        }

        $this->logger->info("created goproxies subuser $userName");
        $secret = @json_decode($response->body, true)['secret'];
        if (!is_string($secret) || strlen($secret) < 10) {
            $this->logger->warning("failed to get goproxies secret for $userName: {$response->body}");

            return null;
        }

        return $secret;
    }

}