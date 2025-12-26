<?php

namespace AwardWallet\ExtensionWorker;

use AwardWallet\Common\Parsing\Exception\ErrorFormatter;
use AwardWallet\ExtensionWorker\Commands\CompleteRequest;
use AwardWallet\ExtensionWorker\Commands\NewTabRequest;
use AwardWallet\ExtensionWorker\Commands\SaveLoginIdRequest;
use Psr\Log\LoggerInterface;

class Client {

    private Communicator $communicator;
    private LoggerInterface $logger;
    private FileLogger $fileLogger;
    private ErrorFormatter $errorFormatter;

    public function __construct(Communicator $communicator, LoggerInterface $logger, FileLogger $fileLogger, ErrorFormatter $errorFormatter) {
        $this->communicator = $communicator;
        $this->logger = $logger;
        $this->fileLogger = $fileLogger;
        $this->errorFormatter = $errorFormatter;
    }

    public function newTab($url, $active) : Tab
    {
        $extensionRequest = new ExtensionRequest("newTab", new NewTabRequest($url, $active));
        $response = $this->communicator->sendMessageToExtension($extensionRequest);
        return new Tab($response["tabId"], $this->communicator, 0, $this->logger, $this->fileLogger, $this->errorFormatter);
    }

    public function complete(?string $error = null) : void
    {
        $extensionRequest = new ExtensionRequest("complete", new CompleteRequest($error));
        $this->communicator->sendMessageToExtension($extensionRequest);
    }

    public function saveLoginId(string $loginId, string $login) : void
    {
        $extensionRequest = new ExtensionRequest("saveLoginId", new SaveLoginIdRequest($loginId, $login));
        $this->communicator->sendMessageToExtension($extensionRequest);
    }

    public function getCommunicator(): Communicator
    {
        return $this->communicator;
    }

}
