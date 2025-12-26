<?php

namespace AwardWallet\ExtensionWorker;

use Psr\Log\LoggerInterface;

/**
 * @deprecated Use $master->setWarning instead
 */
class WarningLogger
{

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

}