<?php

namespace AwardWallet\MainBundle\Service;

use AwardWallet\ExtensionWorker\NotificationSenderInterface;
use Psr\Log\LoggerInterface;

class DummyNotificationSender implements NotificationSenderInterface
{

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    public function sendNotification(?string $title = null, ?string $body = null): void
    {
        $this->logger->warning('sendNotification: ' . $title);
        if ($body) {
            $this->logger->warning('notification body: ' . $body);
        }
    }
}