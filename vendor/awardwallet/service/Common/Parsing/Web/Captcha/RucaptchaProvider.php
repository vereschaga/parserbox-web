<?php

namespace AwardWallet\Common\Parsing\Web\Captcha;

use Psr\Log\LoggerInterface;

class RucaptchaProvider implements CaptchaProviderInterface
{

    public const ID = 'rucaptcha';

    private LoggerInterface $logger;
    private string $rucaptchaApiKey;

    public function __construct(LoggerInterface $logger, string $rucaptchaApiKey)
    {
        $this->logger = $logger;
        $this->rucaptchaApiKey = $rucaptchaApiKey;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function recognize(string $key, Context $context, array $options = []): CaptchaProviderResult
    {
        $recognizer = $this->createRecognizer();

        if (empty($options['userAgent'])) {
            $options['userAgent'] = $context->getUserAgent();
        }

        try {
            $solvedCaptcha = trim($recognizer->recognizeByRuCaptcha($key, $options));
        }
        catch (\CaptchaException $e) {
            $this->logger->warning("[CaptchaException]: " . $e->getMessage());

            if (strstr($e->getMessage(), "ERROR_ZERO_BALANCE")) {
                return new CaptchaProviderResult(CaptchaProviderResult::STATUS_ZERO_BALANCE);
            }

            if (strstr($e->getMessage(), "ERROR_ACCOUNT_SUSPENDED")) {
                return new CaptchaProviderResult(CaptchaProviderResult::STATUS_ACCOUNT_SUSPENDED);
            }

            if (
                ($e->getMessage() == 'server returned error: ERROR_CAPTCHA_UNSOLVABLE'
                    || $e->getMessage() == "timelimit ({$recognizer->RecognizeTimeout}) hit"
                    || $e->getMessage() == 'slot not available'
                    || stristr($e->getMessage(), 'service not available')
                    || $e->getMessage() == 'server returned error: ERROR_IMAGE_TYPE_NOT_SUPPORTED'
                    || $e->getMessage() == 'server returned error: ERROR_PROXY_CONNECTION_FAILED'
                    || strstr($e->getMessage(), 'CURL returned error: Failed to connect to rucaptcha.com port')
                    || strstr($e->getMessage(), 'CURL returned error: Could not resolve host: rucaptcha.com')
                    || strstr($e->getMessage(), 'CURL returned error: Recv failure: Connection reset by peer')
                    || strstr($e->getMessage(), 'CURL returned error: Connection timed out after ')
                    || strstr($e->getMessage(), 'CURL returned error: Operation timed out after 6000'))
            ) {
                return new CaptchaProviderResult(CaptchaProviderResult::STATUS_RETRY);
            }

            if (
                $e->getMessage() == 'server returned error: ERROR_WRONG_CAPTCHA_ID'
            ) {
                $recognizer->reportIncorrectlySolvedCAPTCHA();

                return new CaptchaProviderResult(CaptchaProviderResult::STATUS_RETRY);
            }

            // Antigate may change authorization key for security reasons if we did not log in for 180 days.
            if ($e->getMessage() == 'server returned error: ERROR_KEY_DOES_NOT_EXIST') {
                return new CaptchaProviderResult(CaptchaProviderResult::STATUS_UNKNOWN_KEY);
            }

            return new CaptchaProviderResult(CaptchaProviderResult::STATUS_UNSOLVED);
        }

        // fixed stupid answers
        if (strlen($solvedCaptcha) < 100) {
            $recognizer->reportIncorrectlySolvedCAPTCHA();

            return new CaptchaProviderResult(CaptchaProviderResult::STATUS_RETRY);
        }

        return new CaptchaProviderResult(CaptchaProviderResult::STATUS_SUCCESS, $solvedCaptcha);
    }

    private function createRecognizer() : \CaptchaRecognizer
    {
        $result = new \CaptchaRecognizer();
        $result->RecognizeTimeout = 120;
        $result->APIKey = $this->rucaptchaApiKey;
        $result->domain = "rucaptcha.com";
        $result->OnMessage = array($this->logger, "debug");

        return $result;

    }
}