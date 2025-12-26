<?php

use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;

require_once __DIR__ . '/../airfrance/TAccountCheckerAirfranceSelenium.php';

class TAccountCheckerKlbbluebizSelenium extends TAccountCheckerAirfranceSelenium
{
    use SeleniumCheckerHelper;

    public string $host = 'account.bluebiz.com';

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] != 'nl') {
            return false;
        }

        $this->getCurlChecker();
        if ($this->curlChecker->loginSuccessful()) {
            return true;
        }

        return false;
    }
    public function InitBrowser()
    {
        parent::InitBrowser();
    }

    public function acceptCookies()
    {
        $this->logger->notice(__METHOD__);
        try {
            $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(.,"AGREE")]'), 5);
            if (!$btn) {
                return;
            }
            $btn->click();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        $message = $this->http->FindSingleNode('//div[contains(@class, "bwc-form-errors")]/span')
            ?? $this->http->FindSingleNode('(//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)])[last()]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Incorrect username and/or password. Please check and try again.')
                || strstr($message, 'These login details appear to be incorrect. Please verify the information and try again')
                || strstr($message, 'Sorry, we couldn’t find the e-mail address or Flying Blue number you entered. Please try again or create a Flying Blue account.')
                || strstr($message, 'Sorry, we couldn\'t find the e-mail address or Flying Blue number entered. Please try again or create a Flying Blue account.')
                || strstr($message, 'More than 1 passenger is registered with this e-mail address. Please log in with your Flying Blue number instead, so we can uniquely identify you.')
                || strstr($message, 'The password you entered is not valid. Please try again.')
                || $message == 'Please enter a valid password.'
                || $message == 'Please enter a valid e-mail address.'
                || $message == "Sorry, we can't recognise your password due to a technical error. Please click on \"Forgot password?\" to request a new one."
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Unfortunately, your account is blocked. Please click "Forgot password?" to reset your password.'
                || strstr($message, 'Unfortunately, your account is blocked.')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                $message == 'Sorry, an unexpected technical error occurred. Please try again or contact our customer support.'
                || strstr($message, 'Sorry, we cannot log you in right now. Contact us via the KLM Customer Contact Centre, or 24/7 via social media. Please mention the type of error "account is missing"')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            if (
                strstr($message, 'Authentication failed: recaptchaResponse')
                || strstr($message, 'Access denied: Ineligible captcha score')
                || $message == 'Invalid Captcha'
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }

            if (
                strstr($message, 'Due to a technical error, it is not possible to log you in right now.')
                || strstr($message, 'Due to a technical error, we cannot log you in right now.')
            ) {
                $this->markProxyAsInvalid();

                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $this->DebugInfo = "block, technical error";

                throw new CheckRetryNeededException(2, 0);
            }

            $this->DebugInfo = $message;

            return false;
        }


        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We’re experiencing some unexpected issues, but our team is already working to fix the problem")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        if ($this->http->FindSingleNode('//div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Update your password")]')) {
            $this->throwProfileUpdateMessageException();
        }
        return false;
    }

    public function getCurlChecker()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->curlChecker)) {
            $this->curlChecker = new TAccountCheckerKlbbluebiz();
            $this->curlChecker->http = new HttpBrowser("none", new CurlDriver());
            $this->curlChecker->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->curlChecker->http);
            $this->curlChecker->AccountFields = $this->AccountFields;
            $this->curlChecker->itinerariesMaster = $this->itinerariesMaster;
            $this->curlChecker->HistoryStartDate = $this->HistoryStartDate;
            $this->curlChecker->historyStartDates = $this->historyStartDates;
            $this->curlChecker->http->LogHeaders = $this->http->LogHeaders;
            $this->curlChecker->ParseIts = $this->ParseIts;
            $this->curlChecker->ParsePastIts = $this->ParsePastIts;
            $this->curlChecker->WantHistory = $this->WantHistory;
            $this->curlChecker->WantFiles = $this->WantFiles;
            $this->curlChecker->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);

            $this->curlChecker->globalLogger = $this->globalLogger;
            $this->curlChecker->logger = $this->logger;
            $this->curlChecker->onTimeLimitIncreased = $this->onTimeLimitIncreased;

            $cookies = $this->driver->manage()->getCookies();
            $this->logger->debug("set cookies");

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'currency') {
                    $this->currency = $cookie['value'];
                }
                $this->curlChecker->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        }

        return $this->curlChecker;
    }
}
