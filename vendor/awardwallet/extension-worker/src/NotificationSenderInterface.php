<?php

namespace AwardWallet\ExtensionWorker;

interface NotificationSenderInterface
{

    public function sendNotification(?string $title = null, ?string $body = null) : void;

}