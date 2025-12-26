<?php

namespace AwardWallet\ExtensionWorker;

use Psr\Log\LoggerInterface;

class DebugWatchdogControl implements WatchdogControlInterface
{

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function increaseTimeLimit(int $addedSeconds = 60): void
    {
        $this->logger->debug("increaseTimeLimit: $addedSeconds");
    }

}