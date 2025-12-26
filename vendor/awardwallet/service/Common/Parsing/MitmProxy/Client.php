<?php
namespace AwardWallet\Common\Parsing\MitmProxy;

use AwardWallet\Common\Parsing\Web\HAR\Har;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

class Client
{
    private \HttpDriverInterface $httpDriver;
    private LoggerInterface $logger;
    /**
     * @var int[]
     */
    private array $ports = [];
    /** @var string host:port */
    private string $cacheServer;
    private string $apiUrl;
    private SerializerInterface $serializer;

    public function __construct(\HttpDriverInterface $httpDriver, LoggerInterface $logger, string $cacheServer, string $apiUrl, SerializerInterface $serializer)
    {
        $this->httpDriver = $httpDriver;
        $this->logger = $logger;
        $this->cacheServer = $cacheServer;
        $this->apiUrl = $apiUrl;
        $this->serializer = $serializer;
    }

    /**
     * @return int - port number
     */
    public function createProxyPort(Port $port): int
    {
        $data = [
            'rules' => [],
        ];

        if ($port->getExternalProxies()) {
            $data['ext_proxies'] = $port->getExternalProxies();
        }

        foreach ($port->getBanUrls() as $url) {
            $data['rules'][] = [
                'name' => 'drop',
                'action' => 'drop',
                'url_regexp' => $url,
            ];
        }

        foreach ($port->getCacheUrls() as $url) {
            $data['rules'][] = [
                'name' => 'cache',
                'action' => 'forward',
                'target_proxy' => $this->cacheServer,
                'url_regexp' => $url,
            ];
        }

        $result = $this->sendRequestAndDecode('POST', '/proxies', $data);

        $this->logger->info("created mitm-proxy port: {$result['port']}");
        $this->ports[] = $result['port'];

        return $result['port'];
    }

    public function deleteProxyPort(int $portNumber): void
    {
        $this->logger->info("deleting mitm-proxy port: $portNumber");
        $index = array_search($portNumber, $this->ports);
        if ($index !== false) {
            array_splice($this->ports, $index, 1);
        }

        $this->sendRequest('DELETE', "/proxies/$portNumber", null);
    }

    public function getPortInfo(int $portNumber): PortInfo
    {
        $this->logger->info("get mitm-proxy port info: $portNumber");
        return $this->serializer->deserialize($this->sendRequest('GET', "/proxies/$portNumber", null), PortInfo::class, 'json');
    }

    /**
     * keep port for future checks
     */
    public function keepProxyPort(int $portNumber): void
    {
        $this->logger->info("keep mitm proxy port: $portNumber");
        $index = array_search($portNumber, $this->ports);
        if ($index !== false) {
            array_splice($this->ports, $index, 1);
        }
    }

    public function getHAR(int $portNumber, ?string $regex) : Har
    {
        $params = [
            'port' => $portNumber,
        ];

        if ($regex) {
            $params['search'] = $regex;
        }

        $response = $this->sendRequest('GET', "/logs?" . http_build_query($params));
        /** @var Har $result */
        $result = $this->serializer->deserialize($response, Har::class, 'json');
        $this->logger->info("got " . count($result->log->entries) . " requests from mitm" . ($regex ? " matching $regex" : ""));

        return $result;
    }

    public function deleteAllPorts() : void
    {
        foreach ($this->ports as $port) {
            $this->deleteProxyPort($port);
        }

        $this->ports = [];
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
        return $this->resolveIp(parse_url($this->apiUrl, PHP_URL_HOST));
    }

    private function sendRequestAndDecode(string $method, string $path, ?array $postData = null) : array
    {
        $response = $this->sendRequest($method, $path, $postData);
        $result = @json_decode($response, true);

        if (!is_array($result)) {
            throw new ApiException("mitm-proxy API error - bad json : {$response}");
        }
        
        return $result;
    }

    private function sendRequest(string $method, string $path, ?array $postData = null) : string
    {
        $request = new \HttpDriverRequest($this->apiUrl . $path, $method, json_encode($postData));
        $request->timeout = 30;
        $response = $this->httpDriver->request($request);

        if ($response->httpCode < 200 || $response->httpCode > 299) {
            throw new ApiException("mitm-proxy API error: {$response->errorCode} {$response->errorMessage} {$response->httpCode} {$response->body}");
        }

        return $response->body;
    }

}