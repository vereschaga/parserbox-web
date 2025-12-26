<?php
namespace AwardWallet\ExtensionWorker;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class Communicator
{

    public const DEFAULT_TIMEOUT = 60;

    private string $sessionId;
    private \phpcent\Client $centrifuge;
    private AbstractConnection $rabbitConnection;
    private LoggerInterface $logger;
    private array $lastRequests = [];
    private ?string $userAgent = null;

    public function __construct(
        string $sessionId,
        \phpcent\Client $centrifuge,
        AbstractConnection $rabbitConnection,
        RabbitQueue $rabbitQueue,
        LoggerInterface $logger,
        bool $sessionWasRestored
    )
    {
        $this->sessionId = $sessionId;
        $this->centrifuge = $centrifuge;
        $this->rabbitConnection = $rabbitConnection;
        $this->logger = $logger;

        $channel = $this->rabbitConnection->channel();
        $queue = self::rabbitQueueName($this->sessionId);
        $rabbitQueue->createRabbitQueue($queue);
        if (!$sessionWasRestored) {
            $response = $this->readFromRabbitQueue($channel, $queue, "0");
            $this->logger->info("browser connected: " . json_encode($response) . ", sessionId: {$this->sessionId}");
            if ($response->result["status"] === "ok" && isset($response->result["result"]["userAgent"])) {
                $this->userAgent = $response->result["result"]["userAgent"];
            }
        } else {
            $this->logger->info("communicator restored session, sessionId: {$this->sessionId}");
        }
    }

    public function sendMessageToExtension(ExtensionRequest $message, int $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->centrifuge->publish("#" . $this->sessionId, (array) $message);
        $this->lastRequests[$message->requestId] = $message;
        while (count($this->lastRequests) > 10) {
            array_shift($this->lastRequests);
        }

        try {
            $response = $this->readFromRabbitQueue($this->rabbitConnection->channel(), self::rabbitQueueName($this->sessionId), $message->requestId, $timeout);
        }
        catch (CommunicationException $e) {
            throw new CommunicationException("sendMessageToExtension('{$message->command}'): " . $e->getMessage(), 0, $e);
        }

        if ($response->result["status"] !== "ok") {
            // catch : No tab with id: 1117431119
            throw new CommunicationException("Extension error: " . json_encode($response->result["error"]));
        }

        return $response->result["result"] ?? null;
    }

    public static function rabbitQueueName(string $sessionId) : string
    {
        return "ew-" . $sessionId;
    }

    private function readFromRabbitQueue(AMQPChannel $channel, string $queue, string $requestId, int $timeout = self::DEFAULT_TIMEOUT) : ExtensionResponse
    {
        /** @var ExtensionResponse $response */
        $response = null;
        $consumerTag = $channel->basic_consume($queue, "e-w-communicator-" . $this->sessionId, true, false, false, false, function(AMQPMessage $rabbitMessage) use (&$response, $requestId) {
            $data = json_decode($rabbitMessage->body, true);
            $response = new ExtensionResponse($data['sessionId'], $data['result'], $data['requestId']);

            if ($response->sessionId !== $this->sessionId) {
                $error = "invalid sessionId {$response->sessionId} in session channel {$this->sessionId}";
                $this->logger->error($error);
                throw new CommunicationException("invalid sessionId {$response->sessionId} in session channel {$this->sessionId}");
            }

            if ($response->requestId !== $requestId) {
                $this->logger->notice("unknown requestId {$response->requestId}, expecting {$requestId}"
                    . (isset($this->lastRequests[$response->requestId]) ? (", already processed: " . json_encode((array) $this->lastRequests[$response->requestId])) : ", unknown")
                );
                $rabbitMessage->ack();
                $response = null;
            }
        });

        try {
            do {
                try {
                    $channel->wait(null, false, $timeout);
                } catch (AMQPTimeoutException $exception) {
                    throw new CommunicationException("timed out waiting for response, sessionId: {$this->sessionId}, requestId: {$requestId}");
                }
            } while (count($channel->callbacks) && $response === null);
        } finally {
            $channel->basic_cancel($consumerTag);
        }

        return $response;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

}