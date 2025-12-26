<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;

require_once __DIR__ . '/../airfrance/TAccountCheckerAirfranceSelenium.php';

class TAccountCheckerKlmSelenium extends TAccountCheckerAirfranceSelenium
{
    use SeleniumCheckerHelper;

    public string $host = 'www.klm.com';
    public function InitBrowser()
    {
        parent::InitBrowser();
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
                || strstr($message, 't find the e-mail address or Flying Blue number you entered')
                || strstr($message, 't find the e-mail address or Flying Blue number entered')
                || strstr($message, 'The password you entered is not valid.')
                || strstr($message, 'More than 1 passenger is registered with this e-mail address. Please log in with your Flying Blue number instead, so we can uniquely identify you.')
                || $message == 'Please enter a valid password.'
                || $message == 'Please enter a valid e-mail address.'
                || $message == 'Your temporary password has expired.'
                || $message == 'Please enter a valid password'
                || strstr($message, 'Sorry, we can\'t recognise your password due to a technical error')
                || strstr($message, 'Sorry, we cannot log you in right now. Contact us via the')
                || strstr($message, 'Oops, the login details you entered are incorrect.')
                || strstr($message, 'Your e-mail address seems to be invalid.')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Unfortunately, your account is blocked.')
                || strstr($message, 'Your account is blocked. Please wait 24 hours before clicking "Forgot password?" to reset your password.')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($message, 'Authentication failed: recaptchaResponse')
                || strstr($message, 'Access denied: Ineligible captcha score')
                || $message == 'Invalid Captcha'
            ) {
                $this->captchaReporting($this->recognizer, false);
                $this->DebugInfo = $message;

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

            if (
                $message == 'Retrieved unexpected result from mashery.'
                || $message == 'Forbidden'
                || $message == 'Unexpected technical error has occured'
                || $message == 'Sorry, an unexpected technical error occurred. Please try again later or contact the KLM Customer Contact Centre.'
            ) {
                $this->captchaReporting($this->recognizer);
                $this->DebugInfo = $message;

                throw new CheckException("Sorry, an unexpected technical error occurred. Please try again later or contact the KLM Customer Contact Centre.", ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'Sorry, our system fell asleep. Please restart your login.'
                || strstr($message, 'Communication email is invalid')
                || strstr($message, 'Sorry, we cannot verify your password due to a technical issue')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
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
            $this->curlChecker = new TAccountCheckerKlm();
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
