<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerPieology extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const XPATH_BALANCE = '//span[@class = "c-recent__current-points__points"] | //div[@class = "__points"]/h3';
    private const XPATH_BALANCE_TWO = '//div[contains(@class, "user-points")]/h1 | //div[contains(@class, "user-points")]/h3';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->UseSelenium();
        /*
        $this->useFirefox();
        $this->setKeepProfile(true);
        */
        $this->setProxyBrightData();

        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://order.pieology.com/order/rewards');

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://order.pieology.com/account/login');
        sleep(1);

        $this->waitFor(function () {
            return !is_null($this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //input[@name = 'email'] | //h1[contains(text(), 'Sorry, you have been blocked')]"), 0));
        }, 100);

        if ($this->cloudFlareworkaround($this)) {
            $this->waitFor(function () {
                return !is_null($this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //input[@name = 'email'] | //h1[contains(text(), 'Sorry, you have been blocked')]"), 0));
            }, 100);
            $this->saveResponse();
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "email"]'), 0);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@data-cy = "login-log-in"]'), 0);

        $this->logger->notice("close popup");
        $this->driver->executeScript('
            try {
                document.querySelector(\'#ion-overlay-1\').remove();
            } catch (e) {}
        ');

        if (!$loginInput || !$passwordInput || !$button) {
            $this->saveResponse();

            return false;
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        $this->logger->debug("click by btn");
        $button->click();

        return true;
    }

    public function Login()
    {
        $res = $this->waitForElement(WebDriverBy::xpath(self::XPATH_BALANCE . '
            | //h4[contains(text(), "We updated our system to give you a better experience!")]
            | //div[@data-cy="login-form-error" and contains(@class, "error-wrapper")]/p
            | //div[contains(@class, "user-points")]/h1
            | //h3[contains(text(), "Complete Profile")]
        '), 10);
        $this->saveResponse();

        if (!$res && ($button = $this->waitForElement(WebDriverBy::xpath('//button[@data-cy = "login-log-in"]'), 0))) {
            $button->click();

            $this->waitForElement(WebDriverBy::xpath(self::XPATH_BALANCE . '
                | //h4[contains(text(), "We updated our system to give you a better experience!")]
                | //div[@data-cy="login-form-error" and contains(@class, "error-wrapper")]/p
                | //div[contains(@class, "user-points")]/h1
                | //h3[contains(text(), "Complete Profile")]
            '), 10);
            $this->saveResponse();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@data-cy="login-form-error" and contains(@class, "error-wrapper")]/p')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Invalid login. Please check your credentials and try again')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Unknown Error') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        if ($this->http->FindSingleNode('//h3[contains(text(), "Complete Profile")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode('//h2[contains(text(), "PRIVACY POLICY UPDATE")]')) {
            $this->throwAcceptTermsMessageException();
        }

        if ($this->waitForElement(WebDriverBy::xpath('//h4[contains(text(), "We updated our system to give you a better experience!")]'), 0)) {
            throw new CheckException("We updated our system to give you a better experience! An email has been sent to verify your account and reset your password (we know this is a hassle).", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Current Points
        $this->SetBalance(
            $this->http->FindSingleNode(self::XPATH_BALANCE)
            ?? $this->http->FindSingleNode(self::XPATH_BALANCE_TWO)
        );
        // points until next reward
        $this->SetProperty('ToNextReward', $this->http->FindSingleNode('//p[contains(text(), "until next reward")]', null, true, "/(\d+) point/ims"));

        // Rewards
        $rewards = $this->http->XPath->query('//div[contains(@class, "__rewards__items")]/div | //div[contains(@class, "rewards-slider-desktop_")]//div[contains(@class, "c-reward-card ")]');
        $this->logger->debug("Total {$rewards->length} rewards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            $displayName = $this->http->FindSingleNode('.//h4', $reward);
            $this->logger->debug("[displayName]: {$displayName}");
            $exp = $this->http->FindSingleNode('.//div[contains(@class, "card__img__exp")]/span', $reward);
            $this->logger->debug("Exp: " . $exp);

            $this->AddSubAccount([
                'Code'           => 'Reward' . md5($displayName . $exp),
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => strtotime($exp),
            ], true);
        }// foreach ($rewards as $reward)

        // Name
        $this->http->GetURL('https://order.pieology.com/account/settings');
        $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "name")]'), 10);
        $this->saveResponse();
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//p[contains(@class, "name")]')));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $balance = $this->waitForElement(WebDriverBy::xpath(self::XPATH_BALANCE . ' | ' . self::XPATH_BALANCE_TWO), 3);
        $this->saveResponse();

        if ($balance || $this->http->FindNodes(self::XPATH_BALANCE . ' | ' . self::XPATH_BALANCE_TWO)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}

class TAccountCheckerPieologyPunchhDotCom extends TAccountChecker
{
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, pollotropical, pizzarev, teriyaki, pizzastudio, grubburger, cicispizza, fazolis, tacojohns
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    use SeleniumCheckerHelper;
    use ProxyList;

    public $code = "pieology";
    public $reCaptcha = true;
    public $seleniumAuth = true;
    public $seleniumLastURL = null;
    /**
     * @var CaptchaRecognizer
     */
    public $recognizer;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://iframe.punchh.com/whitelabel/' . $this->code;

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->reCaptcha) {
            $this->http->SetProxy($this->proxyReCaptcha());
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://iframe.punchh.com/whitelabel/' . $this->code, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://iframe.punchh.com/customers/sign_in.iframe?slug=' . $this->code);

        if (!$this->http->ParseForm('user-form')) {
            return $this->checkErrors();
        }

        if ($this->seleniumAuth) {
            $this->seleniumAuth($this->http->currentUrl());

            return true;
        }

        $this->http->SetInputValue('user[email]', $this->AccountFields['Login']);
        $this->http->SetInputValue("user[password]", $this->AccountFields['Pass']);

        if ($this->reCaptcha) {
            $captcha = $this->parseReCaptcha();

            if ($captcha !== false) {
                $this->http->SetInputValue('g-recaptcha-response', $captcha);
            }
        }// if ($this->reCaptcha)

        return true;
    }

    public function Login()
    {
        if ($this->seleniumAuth === false && !$this->http->PostForm()) {
            return $this->checkErrors();
        }

//        if ($this->seleniumAuth === false && $this->http->ParseForm("challenge-form")) {
//            $this->seleniumAuth($this->http->currentUrl());
//        }

        // skip useless update profile, save as is
        if (
            $this->http->currentUrl() == 'https://iframe.punchh.com/whitelabel/' . $this->code . '/user_profile/new.iframe'
            && $this->http->FindSingleNode("//strong[contains(text(), 'Signed in successfully.')]")
            && $this->http->FindSingleNode('//input[@value = "Save Profile"]/@value')
            && $this->http->ParseForm(null, '//div[@class = "user-profile-form"]')
        ) {
            $this->http->FormURL = 'https://iframe.punchh.com/whitelabel/' . $this->code . '/user_profile.iframe';
            $this->http->SetInputValue("commit", "Save+Profile");
            $this->http->PostForm();
        }
        // Please agree on given terms and conditions.
        if (
            $this->http->FindSingleNode('//input[@id="user_terms_and_conditions"]/following-sibling::text()[contains(normalize-space(), "I agree to the ")]')
            && $this->http->FindSingleNode('//strong[normalize-space() = "Please agree on given terms and conditions." or contains(text(), "Favorite Location can\'t be blank") or contains(text(), "Please enter a valid postal zip code.")]')
        ) {
            $this->captchaReporting($this->recognizer);
            $this->throwAcceptTermsMessageException();
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        // Incorrect information submitted
        if ($message = $this->http->FindSingleNode('//div[@class="alert-message"]//strong[contains(text(), "Incorrect information submitted")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Captcha verification failed
        if ($this->reCaptcha
            && ($this->http->FindSingleNode('//div[@class="alert-message"]//strong[contains(text(), "Captcha verification failed")]'))
        ) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 5, self::CAPTCHA_ERROR_MSG);
        }

        if (
            $this->http->currentUrl() == 'https://iframe.punchh.com/whitelabel/' . $this->code . '/user_profile/new.iframe'
            && $this->http->FindSingleNode("//div[contains(text(), 'Select Yes if you would like to subscribe for exclusive offers and promotions.')]")
        ) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Thank you for joining Moe Rewards! You’ll be able to use your Moe Rewards account when you order online or through our mobile app.")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-message")]/p/strong')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "This guest has been deactivated from the loyalty program.")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (
            $this->http->currentUrl() != 'https://iframe.punchh.com/customers/edit.iframe?slug=' . $this->code . '/secure_migration'
            && $this->seleniumLastURL != 'https://iframe.punchh.com/whitelabel/' . $this->code
        ) {
            $this->http->GetURL('https://iframe.punchh.com/whitelabel/' . $this->code);
        }

        if (in_array($this->code, ["cicispizza"])) {
            // Balance - Current Card ... Slices
            $this->SetBalance($this->http->FindSingleNode('//div[@class="iframe-container"]//span[@class="current-checkins"]', null, true, "/^(\d+)\s*(?:Slice|Punch)/"));
            // Available Redeemable cards / Available small combo meals
            $this->SetProperty('AvailableCards', $this->http->FindSingleNode('//div[@class="iframe-container"]//span[@class = "current-redeemable-card"]'));
            // ... more to go to fill up card / ... more punches to go - Slices to Fill Up Card
            $this->SetProperty('ToNextReward', $this->http->FindSingleNode('//div[@class="iframe-container"]//span[@class = "current-checkins-left"]'));
        } else {
            // Balance - Current Points
            $this->SetBalance($this->http->FindSingleNode('//div[@class="iframe-container"]//span[@class="current-points"]'));
        }
        // Membership Level
        if (in_array($this->code, ["freebirds", "coffeebean", "moes", "saladworks", "vinovolo", "tucanos", "condadotacos"])) {
            $this->SetProperty('Tier', $this->http->FindSingleNode('//div[@class="iframe-container"]//span[@class="membership-level"]'));
        }
        // Banked Rewards
        if (in_array($this->code, [
            "maxnermas",
            "lunagrill",
            "piefive",
            "moes",
            "saladworks",
            "beefsteak",
            "tucanos",
            "eploco",
            "pollotropical",
            "grubburger",
            "condadotacos",
            "graeters",
            "jimmyjohns",
            "bibibop",
        ])) {
            $this->SetProperty('BankedRewards', $this->http->FindSingleNode("//span[@class='banked-rewards']"));
        }

        if (in_array($this->code, [
            "eploco",
        ])) {
            $this->SetProperty('Level', $this->http->FindSingleNode("//span[@class='membership-level']"));
        }
        // Name
        if ($this->http->currentUrl() != 'https://iframe.punchh.com/customers/edit.iframe?slug=' . $this->code) {
            $this->http->GetURL('https://iframe.punchh.com/customers/edit.iframe?slug=' . $this->code);
        }
        $this->SetProperty('Name', beautifulName(trim($this->http->FindSingleNode('//input[@id="user_first_name"]/@value') . ' ' . $this->http->FindSingleNode('//input[@id="user_last_name"]/@value'))));
        // Membership Card Number
        if (in_array($this->code, ["maxnermas"])) {
            $this->SetProperty('Card', $this->http->FindSingleNode("//input[@id='user_card_number']/@value"));
        }

//        $this->http->GetURL('https://iframe.punchh.com/whitelabel/'.$this->code.'/gift_cards.iframe');

        // Rewards
        $this->http->GetURL('https://iframe.punchh.com/whitelabel/' . $this->code . '/offers');
        $rewards = $this->http->XPath->query("//select[@id = 'redemption_reward_id']/option");
        $this->logger->debug("Total {$rewards->length} rewards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            if (!empty($reward->getAttribute('data-expiry_date'))) {
                $this->logger->debug("Exp v.1: " . $reward->getAttribute('data-expiry_date'));
                $exp = strtotime($reward->getAttribute('data-expiry_date'), false);
            } elseif (!empty($reward->getAttribute('end_date')) && !stristr($reward->nodeValue, '(Never Expires)')) {
                $this->logger->debug("Exp v.2: " . $reward->getAttribute('end_date'));
                $exp = strtotime(str_replace(',', '', $reward->getAttribute('end_date')), false);
            } else {
                $exp = false;
            }
            $this->logger->debug("Exp: " . $exp);
            $displayName = Html::cleanXMLValue($reward->nodeValue);
            $this->logger->debug("[displayName]: {$displayName}");

            if (strstr($displayName, '(Never Expires)')) {
                $displayName = preg_replace('/\s*\(Never Expires\)/ims', '', $displayName);
            }

            if (strstr($displayName, '(Expires on: ')) {
                // refs #21640
                $this->logger->debug("set exp date from displayName");
                $exp = strtotime($this->http->FindPreg('/\s*\(Expires on:([^)]+)\)/ims', false, $displayName), false);

                $displayName = preg_replace('/\s*\(Expires on:[^)]+\)/ims', '', $displayName);
            }
            $this->AddSubAccount([
                'Code'           => $this->code . 'Reward' . md5($displayName . $exp),
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => $exp,
            ], true);
        }// foreach ($rewards as $reward)

        $this->parseExtendedProperties();

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode('//strong[
                    contains(text(), "Birthday can\'t be blank.")
                    or contains(text(), "Please enter a valid phone number.")
                ]')
                && $this->http->currentUrl() == 'https://iframe.punchh.com/customers/edit.iframe?slug=' . $this->code
                && !empty($this->Properties['Name'])
            ) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->http->FindSingleNode('//strong[
                    contains(text(), "Please agree on given terms and conditions.")
                    or contains(text(), "Please select your Favorite Location")
                ]')
                && $this->http->currentUrl() == 'https://iframe.punchh.com/customers/edit.iframe?slug=' . $this->code
                && !empty($this->Properties['Name'])
            ) {
                $this->throwAcceptTermsMessageException();
            }
        }
    }

    public function parseExtendedProperties()
    {
        $this->logger->notice(__METHOD__);
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            //            "method"  => 'turnstile',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@href, "sign_out")]/@href')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[
                contains(text(), "503 Service Temporarily Unavailable")
                or contains(text(), "502 Bad Gateway")
            ]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, but something went wrong. We\'ve been notified about this issue and we\'ll take a look at it shortly.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function seleniumAuth($loginURL)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->setKeepProfile(true);
            $selenium->setProxyNetNut();
            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            /*
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            */
//            $selenium->useFirefox();
//            $selenium->setKeepProfile(true);

//            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL($loginURL);

            $selenium->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@class = 'hcaptcha-box']/iframe | //input[@name = 'user[email]']"), 100);
            $this->savePageToLogs($selenium);

            if ($this->clickCloudFlareCheckboxByMouse($selenium)) {
                $selenium->waitFor(function () use ($selenium) {
                    return !is_null($selenium->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@class = 'hcaptcha-box']/iframe | //input[@name = 'user[email]']"), 0));
                }, 100);
                $this->savePageToLogs($selenium);
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "user[email]"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "user[password]"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//input[@value = "Login" or @value = "Log In"]'), 5);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $this->savePageToLogs($selenium);

            if ($this->reCaptcha) {
                $xOffset = 90;

                if (in_array($this->code, [
                    "lunagrill",
                    "teriyakimadness",
                ])) {
                    $xOffset = 500;
                } elseif (in_array($this->code, [
                    "eploco",
                    "auntieannes",
                ])) {
                    $xOffset = 170;
                } elseif (in_array($this->code, [
                    "mcalisters",
                ])) {
                    $xOffset = 100;
                }

                if ($this->clickCloudFlareCheckboxByMouse($selenium, '//div[contains(@class, "cf-turnstile-wrapper")] | //div[contains(@style, "margin: 0px; padding: 0px;")] | //div[@class="g-recaptcha"]/div', $xOffset)) {
                    sleep(3);
                    $this->savePageToLogs($selenium);
                } else {
                    $captcha = $this->parseReCaptcha();

                    if ($captcha !== false) {
                        $this->http->SetInputValue('g-recaptcha-response', $captcha);
                    }

                    $this->logger->debug("set captcha g-recaptcha-response");
                    $selenium->driver->executeScript("document.querySelector('[name = \"g-recaptcha-response\"]').value = '{$captcha}';");
//                    $selenium->driver->executeScript("document.querySelector('[name = \"cf-turnstile-response\"]').value = '{$captcha}';");
                }
            }// if ($this->reCaptcha)

            $this->logger->debug("click by btn");
            $selenium->driver->executeScript("document.querySelector('#user-form').submit();");
//            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@href, "sign_out")]'), 10);
            $this->savePageToLogs($selenium);

            if ($this->clickCloudFlareCheckboxByMouse($selenium)) {
                $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@href, "sign_out")]'), 10);
                $this->savePageToLogs($selenium);
            }

            // skip useless update profile, save as is
            if (
                $selenium->http->currentUrl() == 'https://iframe.punchh.com/whitelabel/' . $this->code . '/user_profile/new.iframe'
                && $this->http->FindSingleNode("//strong[contains(text(), 'Signed in successfully.')]")
                && $this->http->FindSingleNode('(//input[@value = "Save Profile" or @value = "Go to Profile" and not(@style="display: none;") and not(@data-disable-with)]/@value)[1]')
                && $this->http->ParseForm(null, '//div[@class = "user-profile-form"]')
            ) {
                $btn =
                    $selenium->waitForElement(WebDriverBy::xpath('//input[@value = "Save Profile"]'), 0)
                    ?? $selenium->waitForElement(WebDriverBy::xpath('//input[@value = "Go to Profile"]'), 0);
                $btn->click();
                $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@href, "sign_out")]'), 10);
                $this->savePageToLogs($selenium);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->seleniumLastURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->seleniumLastURL}");
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }
}
