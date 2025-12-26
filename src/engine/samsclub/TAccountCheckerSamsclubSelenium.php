<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSamsclubSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    protected const XPATH_QUESTION = '//h2[contains(text(), "2-step verification")] | //div[@class = "sc-2fa-enroll-mfa-header" and contains(text(), "Verification")]';
    protected const XPATH_SUCCESS = '//div[contains(text(), "Your account")]';
    protected const XPATH_ERROR = '//div[contains(@class, "sc-alert-error")]/span | //div[@class = "sc-input-box-error-block"] | //div[@class = "bst-alert-body"]';
    protected const XPATH_CAPTCHA = '//div[contains(text(), "Let us know you’re human (no robots allowed)")]';

    protected const XPATH_RESULT = [self::XPATH_SUCCESS, self::XPATH_ERROR];
    protected const XPATH_CHECKPOINT = [self::XPATH_SUCCESS, self::XPATH_ERROR, self::XPATH_QUESTION];

    private const REWARDS_PAGE_URL = 'https://www.samsclub.com/api/node/vivaldi/account/v3/membership';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    /** @var HttpBrowser */
    private $browser;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        /* works without sq on some accounts
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        */
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $this->http->SetProxy($this->proxyReCaptcha(), false);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->setKeepProfile(true);
        $this->disableImages();
        $this->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['profile'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $result = $this->loginSuccessful($this->State['profile']);
        $this->http->RetryCount = 2;

        return $result;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->GetURL("https://www.samsclub.com/sams/account/signin/login.jsp");
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }

        $loadingSuccess = $this->waitFor(function () {
            return is_null($this->waitForElement(WebDriverBy::xpath('//span[@aria-label = "Just a moment..."]'), 0));
        }, 10);

        if (!$loadingSuccess) {
            if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_CAPTCHA), 0)) {
                $this->DebugInfo = "captcha";

                throw new CheckRetryNeededException(2, 0);
            }
            $this->saveResponse();
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "email"]'), 10);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Sign In")]]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            if ($this->waitForElement(WebDriverBy::xpath('//span[@class = "first-name" or @class = "sc-header-account-button-name"]'), 0)) {
                return true;
            }

            $this->logger->debug("find iframe");
            $iframe = null;

            try {
                $iframe = $this->driver->findElement(WebDriverBy::xpath('//iframe[contains(@style, "display: block")]'));
            } catch (NoSuchElementException | WebDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            if ($iframe) {
                $this->logger->debug("switch to iframe");
                $this->increaseTimeLimit(200);
                $this->driver->switchTo()->frame($iframe);
                $this->saveResponse();

                if ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Press & Hold")]'), 0)) {
                    throw new CheckRetryNeededException(2, 0);
                }
            }

            return $this->checkErrors();
        }

        $this->logger->debug("set Login");
        $loginInput->sendKeys($this->AccountFields['Login']);
        $this->logger->debug("set Pass");
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        $this->logger->debug("find iframe");
        $this->saveResponse();
        $iframe = null;

        /*
        try {
            $iframe = $this->driver->findElement(WebDriverBy::xpath('//iframe[contains(@style, "display: block")]'));
        } catch (NoSuchElementException | Facebook\WebDriver\Exception\NoSuchElementException $e) {
            $this->logger->error("error: {$e->getMessage()}");
        } catch (WebDriverException $e) {
            $this->logger->error("error: {$e->getMessage()}");
            $this->saveResponse();

            throw new CheckRetryNeededException(2, 0);
        }
//        $iframe = $this->waitForElement(WebDriverBy::xpath('//iframe[contains(@style, "display: block")]'), 0, false);
        if (false && $iframe) {
            $this->logger->debug("switch to iframe");
            $this->increaseTimeLimit(200);
            $this->driver->switchTo()->frame($iframe);
            $this->saveResponse();

            $press = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Press & Hold")]'), 0);

            $mover = new MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->enableCursor();
            $this->saveResponse();
            $mouse = $this->driver->getMouse();

            $this->logger->debug("move to 'press' button");
            $mover->moveToElement($press, ['x' => 20, 'y' => 20]);

            $mouse->mouseDown();
            sleep(30);
            $this->saveResponse();
            sleep(5);
            $this->saveResponse();
            $mouse->mouseUp();

            $this->driver->switchTo()->defaultContent();
            $this->saveResponse();
        }
        */

        try {
            $button->click();
        } catch (UnrecognizedExceptionException | StaleElementReferenceException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            if ($this->waitForElement(WebDriverBy::xpath('//iframe[@id="LL_DataServer" and @name="LL_DataServer"]'), 0)) {
                throw new CheckRetryNeededException(2, 10);
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // SamsClub.com - Site temporarily closed for maintenance
        if ($message = $this->http->FindSingleNode('
                //title[contains(text(), "SamsClub.com - Site temporarily closed for maintenance")]
                | //img[contains(@alt, "SamsClub - Our site is currently undergoing maintenance.")]/@alt
                | //img[contains(@alt, "We\'re down for some planned maintenance")]/@alt
            ')
        ) {
            throw new CheckRetryNeededException(2, 10, $message);
        }

        return false;
    }

    public function Login()
    {
        sleep(3);
        $success = $this->waitForElement(WebDriverBy::xpath(implode(" | ", array_merge(
            self::XPATH_CHECKPOINT,
            []
//            [self::XPATH_CAPTCHA]
        ))), 7);
        $this->saveResponse();

        if (!$success && ($key = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 0))) {
            $this->captchaWorkaround($key);
            sleep(10);
            $this->saveResponse();
            $this->waitForElement(WebDriverBy::xpath(implode(" | ", array_merge(
                self::XPATH_CHECKPOINT
            ))), 0);
            $this->saveResponse();
        }// if (!$success && ($key = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3)))

        if ($this->http->FindSingleNode(self::XPATH_QUESTION)) {
            $emailLabel = $this->waitForElement(WebDriverBy::xpath("//label[contains(text(), '@')] | //p[contains(@class, 'sc-2fa-verification-options-value') and contains(text(), '@')] | //div[contains(@class, 'bst-rad-btn-label') and contains(text(), '@')]"), 0);
            $phoneLabel = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), '(***)-***-')] | //span[contains(text(), '(***) ***-')]"), 0);
            $sendCode = $this->waitForElement(WebDriverBy::xpath("//button[span[contains(text(), 'Send code') or contains(text(), 'Continue')]]"), 0);
            $this->saveResponse();

            if ((empty($emailLabel) && empty($phoneLabel)) || empty($sendCode)) {
                $this->logger->error("something went wrong");

                return false;
            }

            if ($emailLabel) {
                $emailLabel->click();
            }

            $this->saveResponse();

            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $sendCode->click();

            // Sorry, there's a problem. Please try again later. - wtf?
            $error = $this->waitForElement(\WebDriverBy::xpath('//div[contains(@class, "sc-alert-error")]'), 3);
            $this->saveResponse();

            if ($error) {
                $message = $error->getText();
                $this->DebugInfo = $message;

                if ($message == 'Sorry, you can only send up to 3 codes. Please try again later') {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                throw new CheckRetryNeededException(2, 0);
            }

            return $this->process2fa();
        }

        try {
            $profileId = $this->getProfileId();
        } catch (\WebDriverException $e) {
            $this->logger->error("WebDriverException: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }

        if (!isset($profileId)) {
            $this->logger->error("profile id not found");

            $message = $this->http->FindSingleNode(self::XPATH_ERROR);

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $message === "Your email address and password don't match. Please try again or reset your password."
                    || $message === "Please check your email address and try again"
                    || $message === "Invalid password length"
                    || $message === "Your email address and password don’t match. Please try again or reset your password."
                    || $message === "This membership number isn't in use. Please join again or contact us"
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message === "Please call (888) 746-7726 so we can help you with this membership"
                    || $message === "Sorry, there's a problem. Please try again later."
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    $message === "Your membership card was recently reported lost or stolen. For your security, please enter your information again"
                ) {
                    $this->throwProfileUpdateMessageException();
                }

                $this->DebugInfo = $message;
            }

            if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_CAPTCHA), 0)) {
                $this->DebugInfo = "captcha";

                throw new CheckRetryNeededException(2, 0);
            }

            if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'For your security, reset your password.')]"), 0)) {
                throw new CheckException("For your security, reset your password", ACCOUNT_LOCKOUT);
            }

            return false;
        }

        if ($profileId) {
            $this->parseWithCurl();
            $this->State['profile'] = $profileId;
            $this->http->GetURL(self::REWARDS_PAGE_URL);
            $response = $this->http->JsonLog(null, 0);

            return true;
        }

        if ($this->loginSuccessful($profileId)) {
            return true;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        return $this->process2fa();
    }

    public function getProfileInfo()
    {
        $this->logger->notice(__METHOD__);
        $log = 0;

        if ($this->browser->currentUrl() != self::REWARDS_PAGE_URL) {
            //$this->browser->GetURL("https://www.samsclub.com/account");
            $headers = [
                'response_groups' => 'PRIMARY',
                'Accept'          => 'application/json, text/plain, */*',
                'Content-Type'    => 'application/json',
            ];
            $this->browser->RetryCount = 0;
            $this->browser->GetURL(self::REWARDS_PAGE_URL, $headers);
            $this->browser->RetryCount = 2;
            $log = 3;
        }

        return $this->browser->JsonLog(null, $log);
    }

    public function Parse()
    {
        // Club name
        $this->SetProperty("YourClub", urldecode($this->browser->getCookieByName("myPreferredClubName")));

        $response = $this->browser->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'));
//        $response = $this->getProfileInfo();

        if (!isset($response->payload->member, $response->payload->member[0]->memberName->firstName)) {
            // Something went wrong. (AccountID: 3346098)
            if ($this->http->FindPreg('/^\{"status":"FAILURE","statusCode":403,"errCode":"MEMBERSHIP.403.UNEXPECTED_ERROR","message":"Profile \d+ is not authorized to access Membership \d+"\}/')) {
                $this->browser->RetryCount = 0;
                $this->browser->GetURL("https://www.samsclub.com/api/node/vivaldi/v2/instant-savings/summary");
                $this->browser->RetryCount = 2;

                if ($this->browser->Response['body'] == '{"statusCode":500,"error":"Internal Server Error","message":"An internal server error occurred"}') {
                    throw new CheckException("Something went wrong.", ACCOUNT_PROVIDER_ERROR);
                }

                //$this->browser->GetURL("https://www.samsclub.com/account?xid=hdr_account_cash-rewards");
                $headers['response_groups'] = 'PRIMARY';
                $this->browser->GetURL(self::REWARDS_PAGE_URL, $headers);
                $this->browser->JsonLog();

                if ($this->browser->FindPreg('/^\{"status":"FAILURE","statusCode":403,"errCode":"MEMBERSHIP.403.UNEXPECTED_ERROR","message":"ProfileId \d+ is not authorized to access Membership \d+"\}/')) {
                    throw new CheckException("Something went wrong.", ACCOUNT_PROVIDER_ERROR);
                }
            }

            if ($this->http->FindPreg('/^\{"status":"ERROR","code":"401.REQUIRE_LOGOUT","message":"Requesting logout, API needs fresh token."\}/')) {
                throw new CheckRetryNeededException(3, 0);
            }

            return;
        }
        // Name
        $member = $response->payload->member[0];
        $this->SetProperty("Name", beautifulName($member->memberName->firstName . ' ' . $member->memberName->lastName));
        // Account
        $this->SetProperty("Account", $response->payload->membership->membershipId);
        // Status
        $this->SetProperty("Status", $response->payload->membership->membershipType);
        // Club member since
        if (
            isset($response->payload->membership->startDate)
            && ($memberSince = strtotime($response->payload->membership->startDate, false))
        ) {
            $this->SetProperty("MemberSince", date('Y', $memberSince));
        }

        // Membership Expiration
        if (
            isset($response->payload->renewalInfo->expiryDate)
            && ($exp = $this->browser->FindPreg('/(\d{4}\-\d+\-\d+)T/', false, $response->payload->renewalInfo->expiryDate))
            && ($exp = strtotime($exp, false)) // May 19, 2019
        ) {
            $this->SetProperty("MembershipExpiration", date('M d, Y', $exp));
        }

        $this->http->GetURL("https://www.samsclub.com/api/node/vivaldi/account/v4/membership/member-perks");
//        $this->browser->GetURL("https://www.samsclub.com/api/node/vivaldi/account/v4/membership/member-perks");
        $response = $this->browser->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 3, false, 'savings');
        // in savings
        if (isset($response->savingsTotal)) {
            $this->SetProperty("YTDSavings", "$" . $response->savingsTotal);
        }
        $savings = $response->savings ?? [];

        foreach ($savings as $saving) {
            switch ($saving->name) {
                // Cash Rewards
                case 'cashRewards':
                case 'samsCash':
                    $this->SetProperty('TotalEarnedRewards', "$" . $saving->value);

                    break;
                // Everyday club savings
                case 'compSavings':
                    $this->SetProperty('ClubSavings', "Est. $" . $saving->value);

                    break;
                // Free shipping for Plus
                case 'freeShipping':
                    $this->SetProperty('FreeShipping', "$" . $saving->value);

                    break;
                // Instant Savings
                case 'instantSavings':
                    $this->SetProperty('InstantSavings', "$" . $saving->value);

                    break;

                default:
                    $this->logger->notice("Unknown saving type: {$saving->name}");
            }// switch ($saving->name)
        }// foreach ($savings as $saving)

        // Balance - Cash Rewards (Available now)
//        $this->browser->GetURL("https://www.samsclub.com/api/node/vivaldi/account/v3/sams-wallet?response_group=full");
        $this->http->GetURL("https://www.samsclub.com/api/node/vivaldi/account/v3/sams-wallet?response_group=full");
        $response = $this->browser->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 3, false, 'amount');
        $this->SetBalance($response->payload->storedValueCards->samsRewards->balance->amount ?? null);

        // for Account with zero balance
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !isset($response->payload->storedValueCards)
            && (isset($response->payload->paymentCards) || (isset($response->payload) && $response->payload == new stdClass()))
            && (!empty($this->Properties['ClubSavings']) || !empty($this->Properties['YTDSavings']) || !empty($this->Properties['InstantSavings']))
            && !empty($this->Properties['MemberSince'])
            && !empty($this->Properties['Account'])
        ) {
            $this->SetBalance(0);
        }

        /* for what?
        // logout
        $this->browser->GetURL("https://www.samsclub.com/sams/logout.jsp?signOutSuccessUrl=/sams/homepage.jsp?xid=hdr_account_logout");
        */
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $this->browser->setHttp2(true);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
        $this->browser->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());
    }

    protected function captchaWorkaround($key)
    {
        $this->DebugInfo = 'reCAPTCHA checkbox';
        $captcha = $this->parseReCaptcha($key->getAttribute('data-sitekey'));

        if ($captcha === false) {
            $this->logger->error("failed to recognize captcha");

            return false;
        }
        $this->driver->executeScript('window.handleCaptcha(' . json_encode($captcha) . ')');

        return true;
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        // key from /PXnrdalolX/captcha.js?a=c&u=c7349500-2a90-11e9-a4a6-93984e516e46&v=&m=0
        // $key = '6Le--RIaAAAAABfCAPb-s9ftmoED19PSHCpiePYu';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key || !$this->http->FindSingleNode("//script[contains(@src, '/captcha')]/@src")) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => "https://www.samsclub.com/sams/account/signin/login.jsp",
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful($profileId)
    {
        $this->logger->notice(__METHOD__);

        $this->parseWithCurl();

        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Encoding" => "gzip, deflate, br",
        ];
        $this->browser->RetryCount = 0;
        $this->browser->GetURL(self::REWARDS_PAGE_URL, $headers);
        $this->browser->RetryCount = 2;
        $profileData = $this->browser->JsonLog(null, 3);
        $membershipId = $profileData->payload->member[0]->membershipId ?? null;
        $homeEmail = $profileData->payload->member[0]->homeEmail ?? null;
        $loginEmail = $profileData->payload->member[0]->onlineProfile->loginEmail ?? null;
        $this->logger->debug("[Number]: {$membershipId}");
        $this->logger->debug("[Email]: {$homeEmail}");
        $this->logger->debug("[loginEmail]: {$loginEmail}");
        $this->logger->debug("[Email]: " . strtolower(substr($homeEmail, 0, strpos($homeEmail, '@'))));
        $this->logger->debug("[loginEmail]: " . strtolower(substr($loginEmail, 0, strpos($loginEmail, '@'))));
        $this->logger->debug("[Login]: " . strtolower(substr($this->AccountFields['Login'], 0, strpos($this->AccountFields['Login'], '@'))));

        if (
            $membershipId
            && (
                ($membershipId == $this->AccountFields['Login'])
                || (
                    $homeEmail
                    && (
                        strtolower($homeEmail) == strtolower($this->AccountFields['Login'])
                        || strtolower($loginEmail) == strtolower($loginEmail)
                        || strtolower(substr($homeEmail, 0, strpos($homeEmail, '@'))) == strtolower(substr($this->AccountFields['Login'], 0, strpos($this->AccountFields['Login'], '@')))
                    )
                )
            )
        ) {
            $this->State['profile'] = $profileId;

            return true;
        }

        return false;
    }

    private function process2fa(): bool
    {
        $this->logger->notice(__METHOD__);

        $q = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Enter the 6-digit code we sent t')]"), 10);
        $this->saveResponse();

        if (empty($q)) {
            $this->logger->error("something went wrong");

            return false;
        }

        $this->holdSession();
        $question = $q->getText();

        if (!isset($this->Answers[$question])) {
            $this->logger->notice("answer not found");
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $codeInput = $this->waitForElement(WebDriverBy::xpath("//input[@class = 'sc-passcode-box-input']"), 0);
        $sendCode = $this->waitForElement(WebDriverBy::xpath("//button[span[contains(text(), 'Done')]]"), 0);

        if (
            empty($codeInput)
            || empty($sendCode)
        ) {
            $this->logger->error("something went wrong");

            return false;
        }

        $answerInputs = $this->driver->findElements(WebDriverBy::xpath("//input[@class = 'sc-passcode-box-input']"));

        $this->logger->debug("entering answer...");
        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);
//        $codeInput->sendKeys($this->Answers[$question]);

        foreach ($answerInputs as $i => $element) {
            $this->logger->debug("#{$i}: {$answer[$i]}");
            $answerInputs[$i]->clear();
            $answerInputs[$i]->sendKeys($answer[$i]);
            $this->saveResponse();
        }

        $sendCode->click();

        sleep(5);

        $this->waitForElement(\WebDriverBy::xpath(implode(" | ", array_merge(
            self::XPATH_RESULT,
            ['//div[contains(@class, "sc-alert-error")]']
        ))), 0);
        $this->saveResponse();

        if ($error = $this->waitForElement(\WebDriverBy::xpath(self::XPATH_ERROR), 0) ?? $this->waitForElement(\WebDriverBy::xpath('//div[contains(@class, "sc-alert-error")]'), 0)) {
            $codeInput->clear();
            $this->holdSession();

            if (strstr($error->getText(), 'Let us know you’re human (no robots allowed)')) {
                $this->DebugInfo = 'detected as bot';

                return false;
            }

            $this->AskQuestion($question, $error->getText(), "Question");

            return false;
        }

        $profileId = $this->getProfileId();

        if (!isset($profileId)) {
            $this->logger->error("profile id not found");

            return false;
        }

        return $this->loginSuccessful($profileId);
    }

    private function getProfileId()
    {
        $this->logger->notice(__METHOD__);
        $profileId = null;

        $access_token = null;
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            if ($cookie['name'] !== 'authToken') {
                continue;
            }
            $access_token = $cookie['value'];

            break;
        }

        foreach (explode('.', $access_token) as $str) {
            $str = base64_decode($str);
//            $this->http->JsonLog($str);
            $this->logger->debug($str);

            if ($profileId = $this->http->FindPreg('/"mi":"(.+?)"/', false, $str)) {
                break;
            }
        }

        return $profileId;
    }
}
