<?php
namespace AwardWallet\Common\Parsing\LuminatiProxyManager;

use AwardWallet\Common\Parsing\Web\HAR\Har;
use Psr\Log\LoggerInterface;

class Client
{
    /** @var Api */
    private $api;
    /** @var LoggerInterface */
    private $logger;
    /**
     * @var int[]
     */
    private array $ports = [];
    private string $cacheServerHost;

    public function __construct(Api $api, LoggerInterface $logger, string $cacheServerHost)
    {
        $this->api = $api;
        $this->logger = $logger;
        $this->cacheServerHost = $cacheServerHost;
    }

    /**
     * Method for create port at LPM server
     * @param Port $port
     * @return int $portNumber
     */
    public function createProxyPort(Port $port): CreatePortResponse
    {
        $data = $port->getData();
        [$data, $cachePort] = $this->addCachePort($data);

        $result = $this->api->createProxyPort($data);

        $this->logger->info("created lpm port: {$result->data->port}");
        $this->ports[] = $result->data->port;

        return new CreatePortResponse($result->data->port, $cachePort);
    }

    /**
     * Method for delete transmitted port at LPM server
     * @param int $portNumber
     * @return bool
     */
    public function deleteProxyPort(int $portNumber): bool
    {
        $this->logger->info("deleting lpm port: $portNumber");
        $index = array_search($portNumber, $this->ports);
        if ($index !== false) {
            array_splice($this->ports, $index, 1);
        }

        return $this->api->deleteProxyPort($portNumber);
    }

    /**
     * Method to keep port at LPM server, for future checks
     * @param int $portNumber
     * @return bool
     */
    public function keepProxyPort(int $portNumber): void
    {
        $this->logger->info("keep lpm port: $portNumber");
        $index = array_search($portNumber, $this->ports);
        if ($index !== false) {
            array_splice($this->ports, $index, 1);
        }
    }

    public function getHAR(int $portNumber, ?string $regex) : Har
    {
        $params = [
            'port_from' => $portNumber,
            'port_to' => $portNumber,
        ];

        if ($regex) {
            $params['search'] = $regex;
        }

        $response = $this->api->getHarLogs($params);
        $this->logger->info("got {$response->total} requests from lpm" . ($regex ? " matching $regex" : ""));

        return $response;
    }

    public function deleteAllPorts() : void
    {
        foreach ($this->ports as $port) {
            $this->deleteProxyPort($port);
        }

        $this->ports = [];
    }

    public function getRecentStats() : array
    {
        return $this->api->getRecentStats();
    }

    private function resolveIp(string $host) : string
    {
        if ($host === 'host.docker.internal') {
            // local copy, resolves to some internal value 0.250.250.254

            return $host;
        }

        // resolve host to ip, because some selenium servers (macs) does not have correct dns resolution for internal aws dnsm like lpm.infra.awardwallet.com
        return gethostbyname($host);
    }

    /**
     * Return lpm address
     * @return string
     */
    public function getInternalIp(): string
    {
        return $this->resolveIp($this->api->getHost());
    }

    /**
     * creates a cache port if we have cache rules
     * @return array [array $data, ?int $cachePort]
     */
    private function addCachePort(array $data) : array
    {
        $haveCacheRules = isset($data['proxy']['rules']) && count(array_filter($data['proxy']['rules'], fn($rule) => $rule['action_type'] === 'retry_port')) > 0;

        if (!$haveCacheRules) {
            return [$data, null];
        }

        $cachePort = $this->createProxyPort((new Port())->setExternalProxy([$this->resolveIp($this->cacheServerHost) . ':3128']))->getPortNumber();
        $this->logger->info("created lpm cache port: $cachePort");
        foreach ($data['proxy']['rules'] as &$rule) {
            if ($rule['action_type'] === 'retry_port') {
                $rule['action']['retry_port'] = $cachePort;
            }
        }

        return [$data, $cachePort];
    }
}