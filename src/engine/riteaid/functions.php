<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerRiteaid extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.riteaid.com/ra-dashboard';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], "riteaidBonusCash")) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] != 403 && $this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.riteaid.com/login");
        /*
        $loginUrl =
            $this->http->FindSingleNode('(//div[@data-login-api-uri]/@data-login-api-uri)[1]')
            ?? $this->http->FindSingleNode('//script[@id = "login-signup-template"]', null, true, "/data-login-api-uri=\"([^\"]+)/")
        ;

        if (!$loginUrl || !$this->http->ParseForm(null, '//form[contains(@class, "login-signup__loginform")]')) {
        */
        if (!$this->http->FindPreg('/id="login-email-address" name="login-email-address"/')) {
            return $this->checkErrors();
        }

        $this->selenium();

        return true;

        $this->http->unsetInputValue('prescriptionNumber');
        $this->http->unsetInputValue('q');
        $this->http->unsetInputValue('pharmacylogin');
        $this->http->unsetInputValue('store-locator-pickup-time');
        $this->http->unsetInputValue('store-locator-search');

        if (
            $this->http->FindSingleNode('//form[contains(@class, "login-signup__loginform")]/input[@class = "g-recaptcha" and @name = "captchatoken"]/@name')
        ) {
            $captcha = $this->parseReCaptcha();

            if ($captcha == false) {
                return false;
            }
            $this->http->SetInputValue("captchatoken", $captcha);
        }

        $this->http->NormalizeURL($loginUrl);
        $this->http->FormURL = $loginUrl;
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('keepMeLoggedIn', 'true');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are experiencing some technical difficulties, but are working hard to get them resolved as soon as possible.
        if ($message = $this->http->FindSingleNode("
                //h1[contains(text(), 'RiteAid.com is currently under scheduled maintenance and should be available to you again soon.')]
                | //p[contains(text(), \"We’re upgrading your Rite Aid website.\")]
                | //p[contains(text(), 'We are experiencing some technical difficulties, but are working hard to get them resolved as soon as possible.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("
                //h1[contains(text(), 'Internal Server Error - Read')]
            ")
            ?? $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        $brokenRedirectLinks = [
            'https://www.riteaid.com/errors/error500.html',
            'https://www.riteaid.com/shop/404',
        ];
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "Accept"           => "*
        /*",
            "CSRF-Token"       => "undefined",
            "Connection"       => "keep-alive",
        ];
        $this->http->RetryCount = 0;

        if (
            !$this->http->PostForm($headers)
            && !in_array($this->http->currentUrl(), $brokenRedirectLinks)
            && !in_array($this->http->Response['code'], [400])
        ) {
            // There was a problem establishing a connection. Please try again in a few minutes.
            if ($this->http->Response['code'] == 500 && empty($this->http->Response['body'])) {
                throw new CheckException("There was a problem establishing a connection. Please try again in a few minutes.", ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;
        */

        $response = $this->http->JsonLog();
        $status = $response->Status ?? null;

        // provider bug fix
//        if (in_array($this->http->currentUrl(), $brokenRedirectLinks) || $status == 'SUCCESS') {
        if ($this->http->FindSingleNode('//span[contains(@class, "menu__utility--account__username") and not(contains(text(), "Hi, Log In"))]')) {
//            $this->http->GetURL(self::REWARDS_PAGE_URL);

            if ($this->loginSuccessful()) {
                return true;
            }

            $response = $this->http->JsonLog();
            // AccountID: 7071111
            if (
                isset($response->ErrMsg, $response->ErrCde)
                && $response->ErrCde == 'RA0006'
                && $response->ErrMsg == 'Something went wrong. Please contact Customer Care to continue.'
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        $message =
            $response->ErrMsg
            ?? $this->http->FindSingleNode('//div[@class =  "container"]/div[not(contains(@style, "display: none;"))]//div[contains(@class, "inlineErrorMsg") and not(contains(@style, "display: none;"))] | //div[@id = "login-error-text-message" and not(contains(text(), "{{{errorMessage}}}"))] | //div[contains(text(), "Looks like you don\'t have an online account! Let\'s create your account.")] | //p[contains(text(), "Looks like you don’t have an online account!")]')
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Captcha score is out of configured range. Please contact system administrator.')
                || strstr($message, 'Captcha validation failed. Please retry or contact system administrator')
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);
            // Log in failed. Please try again. Note: After 6 attempts your account will be locked.
            if (
                strstr($message, 'Log in failed. Please try again. Note: After 6 attempts your account will be locked.')
                || strstr($message, 'We do not have a record of this account. If you used a different email or mis-typed the information, try again')
                || strstr($message, 'The email address and password combination isn\'t in our records.')
                || strstr($message, 'Looks like you don\'t have an online account!')
                || strstr($message, 'Looks like you don’t have an online account!')
                || $message == 'Please enter a valid email address'
                || $message == 'Please enter a valid email address or username'
                || $message == 'Username or password are not found.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Our system is experiencing difficulties. Please try again in a few minutes.'
                || $message == 'We are facing some internal issue while connecting to our server. Please try after some time.'
                || $message == 'Something went wrong. Please contact Customer Care to continue.'
                || $message == 'There was a problem establishing a connection. Please try again in a few minutes.'
                || $message == 'Failed to get Wellness Dashboard Details.. Please contact Customer Care or try again later.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, "We're sorry, your session has expired. Please log in and try again.")) {
                throw new CheckRetryNeededException(2, 7);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - wellness+ points
        $this->SetBalance($response->Data->DashboardDetails->WellnessPoints);
        // for elite levels tab
        $this->SetProperty('MyPoints', $this->Balance);
        // wellness+ #
        $this->SetProperty("Number", $response->Data->DashboardDetails->WellnessNumber ?? null);
        // credit available from wellness+BonusCash
        $bonusCashRewards = $response->Data->DashboardDetails->BonusCash;

        if (isset($bonusCashRewards)) {
            $this->AddSubAccount([
                "Code"           => "riteaidBonusCash",
                "DisplayName"    => "BonusCash",
                "Balance"        => $bonusCashRewards,
            ]);
        }
        // You can save up to (you can save with Load2Card coupons)
        $this->SetProperty("SaveUpTo", "$" . $response->Data->DashboardDetails->TotalDisplayDollars); //todo : may be wrong

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Your points will be reset to 0 on January 1st, but you'll keep your membership level and discount through June 30, 2021
            // Points data will be unavailable between 12/31/20 - 01/08/2021
            if ($message = $this->http->FindPreg("/Your points will be reset to 0 on January 1st, but you'll keep your membership level and discount.+?through June 30, " . date("Y") . "/u")) {
                $this->SetWarning($message);

                return;
            }
            // Your points will be reset to zero on January 1st, but you'll keep your membership level and discount for all of 2018.
            // Points data will be unavailable between 12/31 - 01/07/2019.
            if ($message = $this->http->FindPreg("#Points data will be unavailable between 12/31 - 01/0\d/" . date("Y") . "#")) {
                $this->SetWarning($message);

                return;
            }
            // Your wellness+ Point Total is temporarily unavailable for mid-year processing.
            if ($message = $this->http->FindPreg("#Point Total is temporarily unavailable for mid-year processing.#")) {
                $this->SetWarning($message);

                return;
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $headers = [
            "Accept"           => "*/*",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL("https://www.riteaid.com/content/riteaid-web/en.ragetwellnessinfo.json", [], $headers);
        $profile = $this->http->JsonLog();
        // Name
        if (isset($profile->Data->firstName)) {
            $this->SetProperty("Name", beautifulName($profile->Data->firstName . " " . $profile->Data->lastName));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // AccountID:4531790
            if ($this->AccountFields['Login'] == "mfaisalahmad"
                && isset(
                    $this->Properties['Name'],
                    $this->Properties['Status'],
                    $this->Properties['Number'],
                    $this->Properties['SaveUpTo'],
                    $this->Properties['SubAccounts']
                )) {
                $this->SetBalanceNA();
            }
        }
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode('(//div[@data-login-api-uri and @data-is-captcha-enabled = "true"]/@data-public-site-key)[1]')
            ?? $this->http->FindSingleNode('//script[@id = "login-signup-template"]', null, true, "/data-public-site-key=([^\s]+)\s*data-is-captcha-enabled=true/")
        ;
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => "https://www.riteaid.com/signup-signin#login",
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => "login",
            "min_score" => 0.3,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        /*
        $postData = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => "https://www.riteaid.com/signup-signin#login",
            "websiteKey" => $key,
            "minScore"   => 0.3,
            "pageAction" => "login",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->PostURL("https://www.riteaid.com/content/riteaid-web/en.ragetdashboarddetails.json", []);
        $response = $this->http->JsonLog();

        if (isset($response->Data->DashboardDetails->WellnessNumber)) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox();
            $selenium->setKeepProfile(true);

            $selenium->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
            /*
            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
            $selenium->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
            */

            $selenium->disableImages();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.riteaid.com/login");
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnknownErrorException: " . $e->getMessage());
                $selenium->http->GetURL("https://www.riteaid.com/login");
            }

            $selenium->waitForElement(WebDriverBy::xpath('
                //input[@id = "login-email-address"]
                | //h1[contains(text(), "Access Denied")]
            '), 40);
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "login-email-address"]'), 0);
            $contBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "email-continue-button"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$contBtn) {
                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    $retry = true;
                }

                return false;
            }

            $this->savePageToLogs($selenium);

            $mouse = $selenium->driver->getMouse();

            $mouse->mouseMove($loginInput->getCoordinates());
            $mouse->click();
            $loginInput->sendKeys($this->AccountFields['Login']);

            $mouse->mouseMove($contBtn->getCoordinates());
            $mouse->click();

            sleep(1);

            $errorXpath = '
                //div[contains(@class, "inlineErrorMsg") and not(contains(@style, "display: none;")) and normalize-space(.) != ""]
                | //div[@id = "login-error-text-message" and not(contains(text(), "{{{errorMessage}}}")) and normalize-space(.) != ""]
                | //p[contains(text(), "We’re upgrading your Rite Aid website.")]
                | //p[contains(text(), "Looks like you don’t have an online account!")]
            ';

            $selenium->waitForElement(WebDriverBy::xpath('
                //input[@id = "login-user-password"]
                | ' . $errorXpath . '
                | //div[contains(text(), "Looks like you don\'t have an online account! Let\'s create your account.")]
            '), 30);

            // password
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "login-user-password"]'), 0);
            $signInBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "pwd-submit-button"]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$passwordInput || !$signInBtn) {
                return false;
            }

            $mouse->mouseMove($passwordInput->getCoordinates());
            $mouse->click();
            $this->savePageToLogs($selenium);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $mouse->mouseMove($signInBtn->getCoordinates());
            $mouse->click();

            $res = $selenium->waitForElement(WebDriverBy::xpath('
                //span[contains(@class, "menu__utility--account__username") and not(contains(text(), "Hi, Log In"))]
                | ' . $errorXpath . '
            '), 50);

            if ($res) {
                $resText = $res->getText();
            }
            // save page to logs
            $this->savePageToLogs($selenium);
            $error = $this->http->FindSingleNode('//div[@id = "login-error-text-message" and not(contains(text(), "{{{errorMessage}}}"))]');
            $this->logger->error("[Error]: {$error}");

            if (
                isset($resText) && strstr($resText, 'Captcha validation failed.')
                || strstr($error, 'Captcha validation failed.')
            ) {
                sleep(30);

                $signInBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "pwd-submit-button"]'), 0);
                $signInBtn->click();

                sleep(10);
                $res = $selenium->waitForElement(WebDriverBy::xpath('
                    //span[contains(@class, "menu__utility--account__username") and not(contains(text(), "Hi, Log In"))]
                    | ' . $errorXpath . '
                '), 20);
                // save page to logs
                $this->savePageToLogs($selenium);

                $error = $this->http->FindSingleNode('//div[@id = "login-error-text-message" and not(contains(text(), "{{{errorMessage}}}"))]');
                $this->logger->error("[Error]: {$error}");

                if (!$res || strstr($error, 'Captcha validation failed.')) {
                    sleep(10);

                    if ($signInBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "pwd-submit-button"]'), 0)) {
                        $signInBtn->click();
                    }

                    sleep(10);
                    $res = $selenium->waitForElement(WebDriverBy::xpath('
                        //span[contains(@class, "menu__utility--account__username") and not(contains(text(), "Hi, Log In"))]
                        | ' . $errorXpath . '
                    '), 5);
                    // save page to logs
                    $this->savePageToLogs($selenium);
                }
            }

            $this->logger->debug("get cookies");
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            $result = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $result;
    }
}
