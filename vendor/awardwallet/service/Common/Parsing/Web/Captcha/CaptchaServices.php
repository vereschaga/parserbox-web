<?php

namespace AwardWallet\Common\Parsing\Web\Captcha;

use AwardWallet\ExtensionWorker\NotificationSenderInterface;
use Psr\Log\LoggerInterface;

class CaptchaServices
{

    public const OPTION_RETRY = 'aw:cs:retry';
    public const OPTION_CHECK_ATTEMPTS = 'aw:cs:check_attempts';
    public const OPTION_CHECK_ATTEMPTS_DELAY = 'aw:cs:check_attenpts_delay';

    private const DEFAULT_OPTIONS = [
        self::OPTION_RETRY => true,
        self::OPTION_CHECK_ATTEMPTS => 3,
        self::OPTION_CHECK_ATTEMPTS_DELAY => 7,
    ];

    private const CAPTCHA_ERROR_MSG = 'We could not recognize captcha. Please try again later.';

    private LoggerInterface $logger;
    private Context $loggingContext;
    private NotificationSenderInterface $notificationSender;
    /**
     * @var CaptchaProviderInterface[]
     */
    private array $captchaProviders;

    private int $captchaCount = 0;
    private int $captchaTime = 0;

    public function __construct(
        LoggerInterface $logger,
        Context         $loggingContext,
        NotificationSenderInterface $notificationSender,
        iterable        $captchaProviders
    )
    {
        $this->logger = $logger;
        $this->loggingContext = $loggingContext;
        $this->notificationSender = $notificationSender;
        $this->captchaProviders = [];
        foreach ($captchaProviders as $provider) {
            $this->captchaProviders[$provider->getId()] = $provider;
        }
    }

    public function recognize(string $key, string $captchaProvider, array $options = []): string
    {
        if (!array_key_exists($captchaProvider, $this->captchaProviders)) {
            throw new \InvalidArgumentException("Captcha provider $captchaProvider not found, we know only: " . implode(', ', array_keys($this->captchaProviders)) . ". Use one of them.");
        }

        $options = array_merge(self::DEFAULT_OPTIONS, $options);

        $startTime = microtime(true);
        $result = $this->captchaProviders[$captchaProvider]->recognize($key, $this->loggingContext, array_diff_key($options, self::DEFAULT_OPTIONS));

        $duration = microtime(true) - $startTime;
        $context = [
            'Partner' => $this->loggingContext->getPartner(),
            'ProviderCode' => $this->loggingContext->getProvider(),
            'RequestAccountID' => $this->loggingContext->getAccountId(),
            "service" => $captchaProvider,
            "Duration" => $duration,
            "CaptchaIndex" => $this->captchaCount
        ];
        $this->logger->info("captcha", $context);

        $this->captchaCount++;
        $this->captchaTime += $duration;

        if ($result->getStatus() === CaptchaProviderResult::STATUS_ZERO_BALANCE) {
            $this->notificationSender->sendNotification("WARNING! $captchaProvider - balance is null");
            throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($result->getStatus() === CaptchaProviderResult::STATUS_ACCOUNT_SUSPENDED) {
            $this->notificationSender->sendNotification("WARNING! $captchaProvider - Account suspended. Contact support via tickets for details.");
            throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($result->getStatus() === CaptchaProviderResult::STATUS_UNKNOWN_KEY) {
            $this->notificationSender->sendNotification("ATTENTION! $captchaProvider - authorization key not exist or has been changed");
            throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($options[self::OPTION_RETRY] && $result->getStatus() === CaptchaProviderResult::STATUS_RETRY) {
            throw new \CheckRetryNeededException($options[self::OPTION_CHECK_ATTEMPTS], $options[self::OPTION_CHECK_ATTEMPTS_DELAY], self::CAPTCHA_ERROR_MSG);
        }

        if ($result->getStatus() !== CaptchaProviderResult::STATUS_SUCCESS) {
            throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $result->getSolvedCode();
    }

}