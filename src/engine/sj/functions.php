<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSj extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const WAIT_TIMEOUT = 10;

    private $accessToken;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        /*
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
        $this->http->saveScreenshots = true;
        */

        $this->useChromePuppeteer();
        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->http->saveScreenshots = true;

        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.sj.se/en', []);
        $this->http->RetryCount = 2;

        $this->waitForElement(WebDriverBy::xpath('//a[@href="/en/my-page" and span[p]]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid Email.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.sj.se/en/login");
        $this->waitForElement(WebDriverBy::xpath('//input[@id="signInName"]'), 30);
        sleep(self::WAIT_TIMEOUT);
        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="signInName"]'), self::WAIT_TIMEOUT);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id="password"]'), 0);
        $signInButton = $this->waitForElement(WebDriverBy::xpath('//button[@id="next"]'), 0);
        $this->saveResponse();

        if (!$login || !$password || !$signInButton) {
            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);

        $this->driver->executeScript("let remember = document.getElementById('rememberMe'); if (remember) remember.checked = true;");
        $this->saveResponse();

        $signInButton->click();

        return true;
    }

    public function Login()
    {
        $submitResult = $this->waitForElement(WebDriverBy::xpath('
            //button[@id="sendCode"]
        '), self::WAIT_TIMEOUT);

        $this->saveResponse();

        if (
            /*
            isset($submitResult) && strstr($submitResult->getTagName(), "button")
            */
            $this->http->FindSingleNode('//button[@id="sendCode"]')
        ) {
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $submitResult->click();

            if ($this->processQuestion()) {
                return false;
            }
        }

        if (
            $message = $this->http->FindSingleNode('//div[contains(@id, "itemLevel-error-signInName") and not(@style="display: none;")]')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $message = $this->http->FindSingleNode('//div[contains(@id, "itemLevel-error-password") and not(@style="display: none;")]')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $message = $this->http->FindSingleNode('//div[contains(@class, "error") and contains(@class, "pageLevel")]/p')
        ) {
            $this->logger->notice("[Error]: {$message}");

            if (
                strstr($message, "Check email address")
                || strstr($message, "The login details don’t match, check the email address and password.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function processQuestion()
    {
        $this->logger->notice(__METHOD__);
        $codeInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "verificationCode"]'), 5);
        $question = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'ve sent a code to your")]'), 0);
        $questionValue = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'ve sent a code to your")]/following-sibling::p'), 0);
        $this->saveResponse();

        if (!$question || !$questionValue || !$codeInput) {
            return false;
        }

        $this->Question = Html::cleanXMLValue($question->getText() . " " . $questionValue->getText());

        if (!isset($this->Answers[$this->Question])) {
            $this->holdSession();
            $this->AskQuestion($this->Question);

            return false;
        }

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $codeInput->click();
        $codeInput->clear();
        $codeInput->sendKeys($answer);

        $this->logger->debug("click button...");
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "verifyCode" and not(contains(@class, "disabled"))]'), 5);
        $this->saveResponse();

        if (!$button) {
            return false;
        }

        $button->click();
        sleep(5);
        $error = $this->waitForElement(WebDriverBy::xpath("//a[contains(@class, 'errorSummaryCard-message')]"), 5);
        $this->saveResponse();

        if ($error) {
            $message = $error->getText();
            $codeInput->clear();

            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Check code')) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $message);
            }

            return false;
        }

        $this->logger->debug("success");
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        return $this->processQuestion();
    }

    public function Parse()
    {
        $script = "
            function getAccessToken() {
                for (const record of Object.keys(localStorage)) {
                    if (record.indexOf('accesstoken') !== -1) {
                        return JSON.parse(localStorage[record]).secret;
                    }
                }
            }

            const subscriptionKey = 'd6625619def348d38be070027fd24ff6';
            const token = getAccessToken();
            const headers = {
                'ocp-apim-subscription-key' : 'd6625619def348d38be070027fd24ff6',
                'Authorization': 'Bearer ' + token
            };

            const fetchConfig = {
                headers,
                mode: 'cors'
            };

            return fetch('https://prod-api.adp.sj.se/public/sj-web/customer/v1/Me/Membership', fetchConfig).then(res => res.json());
        ";

        $membershipInfo = $this->driver->executeScript($script);
        $this->logger->debug(print_r($membershipInfo, true));

        $loyaltyCardNumber = $membershipInfo['loyaltyCardNumber'] ?? null;

        if (isset($loyaltyCardNumber)) {
            // SJ Prio card
            $this->SetProperty('CardNumber', $loyaltyCardNumber);
        }

        $memberSince = $membershipInfo['membershipSince'] ?? null;

        if (isset($memberSince)) {
            // Member since
            $this->SetProperty('MemberSince', $memberSince);
        }

        // Name
        $this->SetProperty('Name', beautifulName(($membershipInfo['firstName'] ?? '') . ' ' . ($membershipInfo['lastName'] ?? '')));
        // Points to To use
        $this->SetBalance($membershipInfo['pointBalance'] ?? null);

        $tier = $membershipInfo['prioLevel'] ?? null;

        switch ($tier) {       // assets/sjse-account-client/App.js
            case 'SJ_PRIO_WHITE':
                $tier = 'White';

                break;

            case 'SJ_PRIO_GREY':
                $tier = 'Grey';

                break;

            case 'SJ_PRIO_BLACK':
                $tier = 'Black';

                break;

            default:
                $tier = null;
                $this->sendNotification('fish - refs #2649 [sj] > valid account :: new tier/level status // IZ');
        }

        // Level
        $this->SetProperty('Tier', $tier);

        $prioLevelPoints = $membershipInfo['prioLevelPoints'] ?? null;

        if (isset($prioLevelPoints)) {
            // Level points
            $this->SetProperty('TierPoints', $prioLevelPoints);
        }

        $pointsToNextLevel = $membershipInfo['pointsToNextLevel'] ?? null;

        if (isset($pointsToNextLevel)) {
            // To $status$ level
            $this->SetProperty('PointsNextTier', $pointsToNextLevel);
        }

        // points expiration
        $pointExpirations = $membershipInfo['pointExpirations'][0] ?? null;

        if (isset($pointExpirations) && !empty($pointExpirations['validThrough']['stopDate'])) {
            $this->sendNotification('refs #24869 - need to check exp date');
            $pointsExpire = [];

            foreach ($membershipInfo['pointExpirations'] as $points) {
                $date = strtotime($points['validThrough']['stopDate']['date'] . ' ' . $points['validThrough']['stopTime']['time']);
                false === $date ?: $pointsExpire[$date] = $points['points'];
            }
            ksort($pointsExpire, SORT_NUMERIC);
            $firstExpire = array_keys($pointsExpire)[0];
            $this->SetProperty('ExpiringPoints', $pointsExpire[$firstExpire]);
            $this->SetExpirationDate($firstExpire);
        }
    }

    public function ParseItineraries()
    {
        $script = "
            function getAccessToken() {
                for (const record of Object.keys(localStorage)) {
                    if (record.indexOf('accesstoken') !== -1) {
                        return JSON.parse(localStorage[record]).secret;
                    }
                }
            }

            const subscriptionKey = 'd6625619def348d38be070027fd24ff6';
            const token = getAccessToken();
            const headers = {
                'ocp-apim-subscription-key' : 'd6625619def348d38be070027fd24ff6',
                'Authorization': 'Bearer ' + token
            };

            const fetchConfig = {
                headers,
                mode: 'cors'
            };

            return fetch('https://prod-api.adp.sj.se/public/sales/secure/booking/v3/bookings?includeCancelledBookings=true', fetchConfig).then(res => res.json());
        ";

        $itinerariesInfo = $this->driver->executeScript($script);
        $this->logger->debug(print_r($itinerariesInfo, true));

        $bookins = $itinerariesInfo['bookings'] ?? [];

        if (count($bookins) > 0) {
            $this->sendNotification('refs #24869 - need to check bookings // IZ');
        }

        return [];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[@data-testid="startLoggedInH1"]')) {
            return true;
        }

        if ($this->http->FindSingleNode('//a[@href="/en/my-page" and span[p[not(contains(text(), "Log in"))]]]')) {
            return true;
        }

        if ($this->http->FindSingleNode('//a[@href="/en/my-page" and span[p[contains(text(), "Log in")]]]')) {
            return false;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        if ($message = $this->http->FindSingleNode('//img[@alt="Under Scheduled Maintenance"]/@alt')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Reload the page. If the problem remains, try again later or contact customer service.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
