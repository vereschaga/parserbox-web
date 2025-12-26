<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerQuidco extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.quidco.com/settings/general/";

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
//        $this->http->SetProxy($this->http->getLiveProxy("https://www.quidco.com/sign-in/", 5, null, [301, 302, 403]));
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.quidco.com/sign-in/");

        if ($this->http->currentUrl() == 'http://www.quidco.com/cookies-disabled/') {
            $this->http->GetURL("https://www.quidco.com/sign-in");
        }

        if (!$this->http->ParseForm("sign-in-page-form")) {
            if (
                isset($this->http->Response['code'])
                && $this->http->Response['code'] == 403
                && $this->http->FindPreg('#(<form id="challenge-form"|<title>Just a moment...</title>)#')
            ) {
                $this->selenium();

                if ($this->ErrorCode != ACCOUNT_CHECKED) {
                    $this->Login();
                }

                return false;
            }

            return $this->checkErrors();
        }

        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("remember_me", 'on');
        $this->http->SetInputValue("login", 'Sign in');

        if ($key = $this->http->FindPreg("/data-sitekey',\s*'([^']+)'\)\;\s*submit\.attr\('data-callback',\s*'reCaptchaCompleted'/")) {
            $captcha = $this->parseCaptcha($key);

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue("g-recaptcha-response", $captcha);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're currently undergoing scheduled site upgrade work.
        if (
            isset($this->http->Response['code'])
            && $this->http->Response['code'] == 503
            && $message = $this->http->FindSingleNode("//h1[contains(text(), 're currently undergoing scheduled site upgrade work.')]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are updating the site at the moment.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Due to the popular demand of Quidco, we are running a little slower than we would like.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to the popular demand of Quidco, we are running a little slower than we would like.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // wrong error

//        // The request could not be satisfied.
//        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'The request could not be satisfied.')]"))
//            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (
            isset($this->http->Response['code'])
            && $this->http->Response['code'] != 403
            && !$this->http->PostForm()
        ) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        // Skip offers
        if ($this->http->FindSingleNode("//img[contains(@src, 'interstitial-images/')]/@src")
            && ($continue = $this->http->FindSingleNode("//a[@id = 'continue']/@href"))
            && !$this->http->FindSingleNode("//span[contains(text(), 'I have read, understand and agree to the')]")
        ) {
            // AccountID: 3293280
//            if (count($this->http->FindNodes('//h3[contains(text(), "Quidco has updated our terms and conditions to incorporate our new conditions of membership")]')) == 2) {
//                $this->throwAcceptTermsMessageException();
//            }

            $this->logger->debug("Skip offers");
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        $error = $this->http->FindPreg("/>error<[^>]+>\s*<[^>]+>\s*<[^>]+>([^<]+)/ims");

        if (!isset($error)) {
            $danger = $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]');
            $this->logger->error("[Error]: {$danger}");

            if ($danger
                && (
                    $this->http->FindPreg('/(?:You have entered an incorrect password\.|Invalid username or password\.|Invalid email address or password\.)/', false, $danger)
                    || strstr($danger, 'Too many failed login attempts. Please try again in one hour')
                    || strstr($danger, 'We have upgraded the security of our website and no longer accept usernames at sign in. ')
                    || strstr($danger, 'Your account has been closed due to your request.')
                    || strstr($danger, 'There is a problem with your account, please contact Customer Support.')
                    || strstr($danger, 'Sign in has failed, please try again')
                )
            ) {
                $error = trim($danger);
            } else {
                $this->DebugInfo = $danger;
            }
        }

        if (isset($error)) {
            $this->captchaReporting($this->recognizer);
            $this->logger->error("[Error]: {$error}");
            // Bug hunting authorization
            switch ($error) {
                case 'Sorry, Quidco is only available for the residents of the UK.':
                case 'Sign in has failed, please try again':// captcha issue
                    $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;

                    break;

                case 'There is a problem with your account, please contact Customer Support.':
                    $this->ErrorCode = ACCOUNT_LOCKOUT;

                    break;

                default:
                    $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            }// switch ($error)

            $this->ErrorMessage = $error;

            return false;
        }
        // Your account has been blocked.
        if ($message = $this->http->FindPreg("/(Your account has been blocked\.)/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // As a security precaution your account has been locked.
        if ($message = $this->http->FindPreg("/As a security precaution your account has been locked\./i")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your accounts has been closed due to your request.
        if ($this->http->FindSingleNode("//div[contains(text(),'Your accounts has been closed due to your request.')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException('Your accounts has been closed due to your request.', ACCOUNT_LOCKOUT);
        }

        // captcha issues
        // We're sorry but we could not verify that you are a human, please try again
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry but we could not verify that you are a human, please try again")]')) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }

        if ($message = $this->http->FindPreg("/Sorry, you have not yet authenticated your Quidco account/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Sorry, you have not yet authenticated your Quidco account. To do this please check your email inbox for our email titled 'Quidco Authentication' and click on the link.", ACCOUNT_INVALID_PASSWORD);
        }
        // Select your Quidco account type
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Select your Quidco account type')]")
            || $this->http->currentUrl() == 'http://www.quidco.com/onboarding/'
            || strstr($this->http->currentUrl(), 'https://www.quidco.com/onboarding/')
        ) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode("//span[contains(text(), 'I have read, understand and agree to the')]")) {
            $this->throwAcceptTermsMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (!stristr($this->http->currentUrl(), self::REWARDS_PAGE_URL)) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Account Number
        $this->SetProperty('AccountNumber', $this->http->FindPreg("/js_user_id = '([^']+)',/ims"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[p[contains(text(), 'Name')]]/following-sibling::div[contains(@class, 'field-value')]/p")));
        // Balance - Your next payment
        /*
//        $this->http->GetURL("https://www.quidco.com/ajax/homepage_feed/badge_and_payment_data?_=" . time() . date("B"));
        $this->http->GetURL("https://www.quidco.com/ajax/main_nav/get_cashback_summary?_=" . date("UB"));
        $response = $this->http->JsonLog(null, 3, true);
        $this->SetBalance(ArrayVal($response, 'total_next_payment'));
        */

        $this->http->GetURL("https://www.quidco.com/activity-summary/");
        // Table 'Activity summary'
        $tr = $this->http->XPath->query("//table[contains(@class, 'table-activity-summary')]//tfoot/tr");
        $this->logger->debug(">>> Total nodes found " . $tr->length);

        if ($tr->length === 1) {
            // Total Paid
            $this->SetProperty("TotalPaid", '£' . $this->http->FindSingleNode("td[@data-th='Total paid']/strong", $tr->item(0)));
            // Outstanding in confirmed
            $this->SetProperty("OutstandingInConfirmed", '£' . $this->http->FindSingleNode("td[3]/text()[last()]", $tr->item(0)));
            // Outstanding in tracked
            $this->SetProperty("TotalTrackedCashback", '£' . $this->http->FindSingleNode("td[4]/text()[last()]", $tr->item(0)));
            // Outstanding claims
            $this->SetProperty("OutstandingClaims", '£' . $this->http->FindSingleNode("td[5]/text()[last()]", $tr->item(0)));
            // Quidco £5
            $this->SetProperty("Quidco5", '£' . $this->http->FindSingleNode("td[6]/text()[last()]", $tr->item(0)));
            // Total Cashback
            $this->SetProperty("TotalCashback", '£' . $this->http->FindSingleNode("td[7]/text()[last()]", $tr->item(0)));
        }// if ($tr->length === 1)
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.quidco.com/sign-in';
        $arg['PreloadAsImages'] = true;
        $arg['SuccessURL'] = 'https://www.quidco.com/home/';

        return $arg;
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        //$key = $this->http->FindPreg("/data-sitekey',\s*'([^']+)'\)\;\s*submit\.attr\('data-callback',\s*'reCaptchaCompleted'/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        /*
        $postData = [
            "type"         => "RecaptchaV2TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "isInvisible" => true
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindNodes("//a[contains(@href, 'logout')]/@href")
            || $this->http->FindPreg("/window\.localStorage\.setItem\('isAutoRefresh', 'true'\)/")
            || $this->http->getCookieByName("cashback_frontend_session")
            || $this->http->getCookieByName("apex__session_id")
            || $this->http->getCookieByName("session_id", ".quidco.com", "/", true)
        ) {
            if (
                /*
                strstr($this->http->currentUrl(), '/?sign_in_redirect_path')
                ||*/
                (isset($this->http->Response['code']) && $this->http->Response['code'] != 200)
            ) {
                return false;
            }

            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

//            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
//            $selenium->seleniumOptions->addHideSeleniumExtension = false;
//            $selenium->seleniumOptions->userAgent = null;

//            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
//            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
//            $selenium->seleniumOptions->addHideSeleniumExtension = false;
//            $selenium->seleniumOptions->userAgent = null;

            // TODO: not working now
            $selenium->useChromePuppeteer();
            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            //$selenium->disableImages();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL("https://www.quidco.com/sign-in/");

            $selenium->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //*[@id = 'onetrust-accept-btn-handler'] | //input[@name = 'login']"), 100);

            if ($this->clickCloudFlareCheckboxByMouse($selenium)) {
                $selenium->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //*[@id = 'onetrust-accept-btn-handler'] | //input[@name = 'login']"), 100);
                $this->savePageToLogs($selenium);
            }

            $cookie = $selenium->waitForElement(WebDriverBy::id("onetrust-accept-btn-handler"), 10);

            if ($cookie) {
                $cookie->click();
            }
            $this->savePageToLogs($selenium);

            if ($key = $this->http->FindPreg("/data-sitekey',\s*'([^']+)'\)\;\s*submit\.attr\('data-callback',\s*'reCaptchaCompleted'/ims")) {
                $captcha = $this->parseCaptcha($key);

                if ($captcha === false) {
                    return false;
                }
                $selenium->driver->executeScript("$('textarea#g-recaptcha-response').val('{$captcha}')");
            }

            $login = $selenium->waitForElement(WebDriverBy::id("username"), 0);
            $pass = $selenium->waitForElement(WebDriverBy::id("password"), 0);
            $this->savePageToLogs($selenium);
            $signInButton = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'login']"), 0);

            if (!$login || !$pass || !$signInButton) {
                return $this->checkErrors();
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            sleep(1);
            if ($key) {
                $selenium->driver->executeScript("reCaptchaCompleted()");
            } else {
                $signInButton->click();
            }

            $selenium->waitForElement(WebDriverBy::xpath("
                //span[contains(@class, 'earned-amount')]
                | //div[contains(@class, 'alert alert-danger')]/div[contains(@class, 'alert-text')]
                | //span[contains(text(), 'I have read, understand and agree to the')]
                | //a[@id = 'continue']
            "), 10);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            if (
                $selenium->http->currentUrl() == 'https://www.quidco.com/home/?asi'
                || $selenium->http->currentUrl() == 'https://www.quidco.com/interstitial/500/?redirect_url=/home/?asi'
                || $selenium->http->currentUrl() == 'https://www.quidco.com/interstitial/502/?redirect_url=/home/?asi'
                || $this->http->FindSingleNode("//a[@id = 'continue']/@href")
            ) {
                $this->logger->info('Parse', ['Header' => 2]);
                $selenium->http->GetURL(self::REWARDS_PAGE_URL);

                // Balance - Your next payment
                $balance = null;
                $b = $selenium->waitForElement(WebDriverBy::xpath("//h3[contains(@class, 'payable-cashback')]"), 10);
                $this->savePageToLogs($selenium);

                if ($b) {
                    $balance = $b->getText();
                }

                $selenium->Parse();

                $this->SetBalance($balance);
                $this->Properties = $selenium->Properties;

                if ($this->ErrorCode != ACCOUNT_CHECKED) {
                    $this->DebugInfo = $selenium->DebugInfo;
                }
                // captcha issue
            } elseif ($this->http->FindSingleNode('//input[@data-callback="reCaptchaCompleted" and @style = ""]/@value')) {
                $retry = true;
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            // retries
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return false;
    }
}
