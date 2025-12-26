<?php

namespace AwardWallet\ExtensionWorker;

use AwardWallet\Common\Parsing\Exception\ErrorFormatter;
use PhpAmqpLib\Connection\AbstractConnection;
use Psr\Log\LoggerInterface;

class ClientFactory
{

    private \phpcent\Client $centrifugeClient;
    private LoggerInterface $logger;
    private AbstractConnection $rabbitConnection;
    private RabbitQueue $rabbitQueue;

    public function __construct(
        \phpcent\Client $centrifuge,
        LoggerInterface $logger,
        AbstractConnection $rabbitConnection,
        RabbitQueue $rabbitQueue
    )
    {
        $this->centrifugeClient = $centrifuge;
        $this->logger = $logger;
        $this->rabbitConnection = $rabbitConnection;
        $this->rabbitQueue = $rabbitQueue;
    }

    public function createClient(string $sessionId, FileLogger $fileLogger, ErrorFormatter $errorFormatter, bool $sessionWasRestored) : Client
    {
        $communicator = new Communicator($sessionId, $this->centrifugeClient, $this->rabbitConnection, $this->rabbitQueue, $this->logger, $sessionWasRestored);

        return new Client($communicator, $this->logger, $fileLogger, $errorFormatter);
    }

}