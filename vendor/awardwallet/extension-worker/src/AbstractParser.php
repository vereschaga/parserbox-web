<?php

namespace AwardWallet\ExtensionWorker;

use AwardWallet\Common\Parsing\Web\Captcha\CaptchaServices;
use Psr\Log\LoggerInterface;

abstract class AbstractParser
{

    protected LoggerInterface $logger;
    protected FileLogger $fileLogger;
    protected ProviderInfo $providerInfo;
    protected NotificationSenderInterface $notificationSender;
    protected StateManager $stateManager;
    protected CaptchaServices $captchaServices;
    protected ParserContext $context;
    protected Waiter $waiter;
    protected WatchdogControlInterface $watchdogControl;

    public function __construct(
        LoggerInterface $logger,
        FileLogger $fileLogger,
        ParserContext $parserContext,
        NotificationSenderInterface $notificationSender,
        StateManager $stateManager,
        CaptchaServices $captchaServices,
        WatchdogControlInterface $watchdogControl
    )
    {
        $this->logger = $logger;
        $this->fileLogger = $fileLogger;
        $this->providerInfo = $parserContext->getProviderInfo();
        $this->context = $parserContext;
        $this->notificationSender = $notificationSender;
        $this->stateManager = $stateManager;
        $this->captchaServices = $captchaServices;
        $this->waiter = new Waiter($logger);
        $this->watchdogControl = $watchdogControl;

        ParserFunctions::load();
    }

}