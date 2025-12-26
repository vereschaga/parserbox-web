<?php

//use AwardWallet\Engine\ProxyList;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerStarbucks extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL_PERU = 'https://www.starbucks.pe/rewards/rewards';
    private const REWARDS_PAGE_URL_SINGAPORE = 'https://www.starbucks.com.sg/rewards';
    private const CRYPTO_IV_THAILAND = '0000000000000000';
    private const CRYPTO_KEY_BASE64_THAILAND = '5cLvZY7PRPYR/avwb8FIFMngAuI9MXOCQHo7zynHeeY=';

    public $regionOptions = [
        ""            => "Select your country",
        "Canada"      => "Canada",
        "China"       => "China",
        "Germany"     => "Germany",
        "HongKong"    => "Hong Kong",
        "India"       => "India",
        "Ireland"     => "Ireland",
        "Japan"       => "Japan",
        "Mexico"      => "Mexico",
        "Peru"        => "Peru",
        "Poland"      => "Poland",
        "Spain"       => "Spain",
        "Singapore"   => "Singapore",
        "Switzerland" => "Switzerland",
//        "Taiwan"      => "Taiwan",
        "Thailand"    => "Thailand",
        "Vietnam"     => "Vietnam",
        "UK"          => "United Kingdom",
        "USA"         => "USA",
    ];
    //use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $domain = 'com';
    private $otherRegion = false;
    private $headersSwitzerland = null;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public static function isClientCheck(AwardWallet\MainBundle\Entity\Account $account, $isMobile)
    {
        if (!in_array($account->getLogin2(), ['USA', 'Canada', ''])) {
            return false;
        }

        return null;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        if ($this->AccountFields['Login2'] == 'Switzerland') {
            $arg['CookieURL'] = "https://card.starbucks.ch/msrservice/Login?format=json&callback=LoginDone&LoginDataString=Emailr0tn1L" . urlencode($this->AccountFields['Login']) . "L1nt0rPasswordr0tn1L" . urlencode($this->AccountFields['Pass']);
            $arg['SuccessURL'] = 'https://card.starbucks.ch/accountdashboard.aspx';
        }

        if ($this->AccountFields['Login2'] == 'China') {
            $arg['RedirectURL'] = "https://www.starbucks.com.cn/en/account";
        }

        return $arg;
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        switch ($this->AccountFields['Login2']) {
            case 'Canada':
                $arg["RedirectURL"] = 'https://www.starbucks.ca/account/signin';

                break;

            case 'China':
                $arg["RedirectURL"] = 'https://www.starbucks.com.cn/account/';

                break;

            case 'Germany':
                $arg["RedirectURL"] = 'https://www.starbucks.de/account/login';

                break;

            case 'HongKong':
                $arg["RedirectURL"] = 'https://card.starbucks.com.hk/account/signin.aspx?lang=en-hk';

                break;

            case 'India':
                $arg["RedirectURL"] = 'https://www.starbucks.in/login';

                break;

            case 'Ireland':
                $arg["RedirectURL"] = 'https://www.starbucks.ie/account/login';

                break;

            case 'Japan':
                $arg["RedirectURL"] = 'https://login.starbucks.co.jp/login';

                break;

            case 'Mexico':
                $arg["RedirectURL"] = 'https://rewards.starbucks.mx/login';

                break;

            case 'Peru':
                $arg["RedirectURL"] = 'https://www.starbucks.pe/rewards/auth/sign-in/rewards/index/rewards';

                break;

            case 'Poland':
                $arg["RedirectURL"] = 'https://card.starbucks.pl/auth/login?language=en';

                break;

            case 'Spain':
                $arg["RedirectURL"] = 'https://www.starbucks.es/account/login';

                break;

            case 'Singapore':
                $arg["RedirectURL"] = 'https://www.starbucks.com.sg/rewards/Login/?ReturnUrl=%2Frewards';

                break;

            case 'Switzerland':
                $arg["RedirectURL"] = 'https://www.starbucks.ch/en/account/login';

                break;

            case 'Taiwan':
                $arg["RedirectURL"] = 'https://myotgcard.starbucks.com.tw/StarbucksMemberWebsite/SignIn.html';

                break;

            case 'Thailand':
                $arg["RedirectURL"] = 'https://www.starbuckscard.in.th/cards/signin.aspx';

                break;

            case 'Vietnam':
                $arg["RedirectURL"] = 'https://card.starbucks.vn/en/sign-in';

                break;

            case 'UK':
                $arg["RedirectURL"] = 'https://www.starbucks.co.uk/account/login';

                break;

            case 'USA':
            default:
                $arg["RedirectURL"] = 'https://www.starbucks.com/account/signin';

                break;
        }
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])) {
            // Peru
            if (strstr($properties['SubAccountCode'], "starbucksCardPeru")) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "S/ %0.2f");
            }
            // Poland
            if (strstr($properties['SubAccountCode'], "starbucksCardPoland")) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f PLN");
            }
            // USA, Canada
            if (strstr($properties['SubAccountCode'], "starbucksCardUSA")
                || strstr($properties['SubAccountCode'], "starbucksCardCanada")
                || strstr($properties['SubAccountCode'], "starbucksCardMexico")) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
            }

            // UK
            if (
                strstr($properties['SubAccountCode'], "starbucksCardUK")
                && !isset($properties['Currency'])
            ) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "£%0.2f");
            }

            if (
                strstr($properties['SubAccountCode'], "starbucksCardUK")
                && $properties['Currency'] == "USD"
            ) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
            }

            if (
                strstr($properties['SubAccountCode'], "starbucksCardUK")
                && $properties['Currency'] == "GBP"
            ) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "£%0.2f");
            }

            // Germany
            if (strstr($properties['SubAccountCode'], "starbucksCardGermany")
                // Spain
                || strstr($properties['SubAccountCode'], "starbucksCardSpain")
                // Ireland
                || strstr($properties['SubAccountCode'], "starbucksCardIreland")) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");
            }
            // Thailand
            if (strstr($properties['SubAccountCode'], "starbucksCardThailand")) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f THB");
            }
            // Switzerland
            if (strstr($properties['SubAccountCode'], "starbucksCardSwitzerland")) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f CHF");
            }
            // Singapore
            if (strstr($properties['SubAccountCode'], "starbucksCardSingapore")) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f SGD");
            }
            // India
            if (strstr($properties['SubAccountCode'], "starbucksCardIndia")) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "₹%0.2f");
            }

            if (strstr($properties['SubAccountCode'], "starbucksCardJapan")) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "¥%0.2f");
            }
            // Hong Kong
            if (strstr($properties['SubAccountCode'], "starbucksCardHongKong")) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "HKD$%0.2f");
            }
        }// if (isset($properties['SubAccountCode']))

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        switch ($this->AccountFields['Login2']) {
            case 'Peru':
            case 'Singapore':
            case 'Thailand':
                if ($this->loginSuccessful()) {
                    return true;
                }

                break;
        }

        return false;
    }

    public static function GetAccountChecker($accountInfo)
    {
        if (in_array($accountInfo['Login2'], [
            'Canada',
            'USA',
            'UK',
            'Germany',
            'Ireland',
            'Spain',
            'Select',
            'Switzerland',
            '',
            'China',
            //            'Singapore',
            'Japan',
        ])) {
            require_once __DIR__ . "/TAccountCheckerStarbucksSelenium.php";

            return new TAccountCheckerStarbucksSelenium();
        }

        return new static();
    }

    public function LoadLoginForm()
    {
        if ($this->AccountFields['Login2'] == 'Taiwan') {
            throw new CheckException("We currently do not support this region. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        switch ($this->AccountFields['Login2']) {
            case 'China':
            case 'HongKong':
            case 'India':
            case 'Japan':
            case 'Mexico':
            case 'Peru':
            case 'Poland':
            case 'Singapore':
            case 'Taiwan':
            case 'Thailand':
            case 'Vietnam':
                $this->otherRegion = true;

                break;

            case 'UK':
                $this->domain = 'co.uk';

                break;

            case 'Switzerland':
                $this->otherRegion = true;
                $this->domain = 'ch';

                break;

            case 'Germany':
                $this->domain = 'de';

                break;

            case 'Canada':
                $this->domain = 'ca';

                break;

            case 'USA':
            default:
                $this->domain = 'com';
        }
        $this->http->removeCookies();

        if (!$this->otherRegion) {
            $this->http->GetURL("https://www.starbucks." . $this->domain . "/account/signin");

            if (!$this->http->ParseForm("accountForm")) {
                return $this->checkErrors();
            }
            $this->http->FormURL = "https://www.starbucks." . $this->domain . "/account/signin";
            $this->http->Form['AllowGuest'] = "False";
            $this->http->Form['sign-in'] = "Sign+In";
            $this->http->Form['Account.UserName'] = $this->AccountFields['Login'];
            $this->http->Form['Account.PassWord'] = $this->AccountFields['Pass'];
        } elseif (!empty($this->AccountFields['Login2']) && method_exists($this, "LoadLoginForm" . $this->AccountFields['Login2'])) {
            return call_user_func([$this, __METHOD__ . $this->AccountFields['Login2']]);
        }

        return true;
    }

    public function LoadLoginFormHongKong()
    {
        $this->logger->notice(__METHOD__);

        $loginIsEmail = filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL);
        $loginIsPhone = preg_match('/^\+85[23]\d{9}$/', $this->AccountFields['Login']);

        if ($loginIsEmail === false
            && $loginIsPhone === false) {
            throw new CheckException("Please enter valid email address or phone number", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://www.starbucks.com.hk/en/");
        $authLink = $this->http->FindSingleNode('//li[@class = "link authorization-link"]/a/@href', null, true, null, 0);

        if (!$authLink) {
            return $this->checkErrors();
        }
        $this->http->GetURL($authLink);

        if (!$this->http->ParseForm('login-form')) {
            return $this->checkErrors();
        }

        if ($loginIsEmail) {
            $this->http->SetInputValue('type', 'email');
            $this->http->SetInputValue('email', $this->AccountFields['Login']);
            $this->http->SetInputValue('emailPassword', $this->AccountFields['Pass']);
        }

        if ($loginIsPhone) {
            $this->http->SetInputValue('type', 'mobile');
            $this->http->SetInputValue('areaCode1', substr($this->AccountFields['Login'], 0, 4));
            $this->http->SetInputValue('mobile1', substr($this->AccountFields['Login'], 4));
            $this->http->SetInputValue('mobilePassword', $this->AccountFields['Pass']);
        }
        $this->http->SetInputValue('stay_signed_in', '1');
        $this->http->SetInputValue('form_key', $this->http->FindSingleNode('//input[@name = "form_key"]/@value'));

//        $captcha = $this->parseCaptcha();
//        if ($captcha === false) {
//            return false;
//        }
//        $this->http->SetInputValue('ctl00$CphMain$TxtCaptcha', $captcha);

        return true;
    }

    public function LoadLoginFormMexico()
    {
        $this->logger->notice(__METHOD__);

        $this->http->SetProxy($this->proxyDOP());

        $this->http->GetURL("https://rewards.starbucks.mx/");
        $client_id = 'rWX6qerwFKvKCVZBXESk9CXzVxwD499k';
        $client_secret = 'NBdre9PdyyVfuNVc8yrfyqFqGvG89HgM';
        $time = time();
        $sig = hash('sha256', $client_id . $client_secret . $time);
        $this->http->PostURL("https://api.starbucks.mx/v1/oauth/token?sig={$sig}", [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'password',
            'password'      => $this->AccountFields['Pass'],
            'timestamp'     => $time,
            'username'      => $this->AccountFields['Login'],
        ], [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'X-Api-Key'    => $client_id,
        ]);

        return true;
    }

    public function LoadLoginFormPeru()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.starbucks.pe/rewards/auth/sign-in/rewards/index/rewards");

        if (!$this->http->ParseForm('form-login')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('User.UserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('User.Password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('User.KeepSession', 'true');

        return true;
    }

    public function LoadLoginFormPoland()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://card.starbucks.pl/auth/login?language=en");

        if (!$this->http->ParseForm(null, "//form[contains(@action,'/application/login')]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('j_username', $this->AccountFields['Login']);
        $this->http->SetInputValue('j_password', $this->AccountFields['Pass']);

        return true;
    }

    public function LoadLoginFormSwitzerland()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.starbucks.ch/en/account/login");

        if (!$this->http->ParseForm("starbucks-login-form")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('keep_signed_in', "on");

        return true;
    }

    public function LoadLoginFormChina()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.starbucks.com.cn/en/log-in");
//        if (!$this->http->ParseForm(null, "//form[contains(@class, 'form')]"))
//            return $this->checkErrors();
        $this->checkErrors();
        $data = [
            "grant_type"  => "client_credentials",
            "remember_me" => "false",
        ];
        $this->http->setDefaultHeader("Accept", "application/json");
        $this->http->setDefaultHeader("Content-Type", "application/json");
        $this->http->PostURL("https://auth.starbucks.com.cn/web/login", json_encode($data),
            // Authorization
//            ["Authorization" => "Basic ".base64_encode("wikipedia18:zqy139189sb")]);//todo
            ["Authorization" => "Basic " . base64_encode($this->AccountFields['Login'] . ":" . $this->AccountFields['Pass'])]);

        return true;
    }

    public function LoadLoginFormThailand()
    {
        $this->logger->notice(__METHOD__);
        // block by ip (country)
        $this->setProxyGoProxies(null, "th");

        $this->http->GetURL('https://rewards.starbucks.co.th/authsignin');

        if (
            $this->http->Response['code'] !== 200
            || !$this->http->FindSingleNode('//title[contains(text(), "Starbucks")]')
        ) {
            return false;
        }

        /*
        $data = [
            'Email'     => $this->AccountFields['Login'],
            'Password'  => $this->AccountFields['Pass'],
            'RefLang'   => 'en',
            'Timestamp' => time(),
        ];

        $this->http->RetryCount = 0;
        $this->postDataThailand('https://www.plusapiprod.com/api/CrmAuthSignin', $data);
        $this->http->RetryCount = 2;
        */

        $selenium = clone $this;
        $xKeys = [];
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->useFirefox();

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://rewards.starbucks.co.th/authsignin");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 10);
            $submitBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign in")]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$submitBtn) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $submitBtn->click();

            sleep(10);// TODO:
            $this->savePageToLogs($selenium);

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                if (stristr($xhr->request->getUri(), '/CrmAuthSignin')) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());
                    $this->http->SetBody($responseData);

                    break;
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();
            $this->logger->debug("set cookies");

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current selenium URL]: {$selenium->http->currentUrl()}");
        } catch (TimeOutException | NoSuchDriverException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
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
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }

    public function LoadLoginFormSingapore()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);

            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL('https://www.starbucks.com.sg/rewards/Login/?ReturnUrl=%2Frewards');

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "email"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $btnSignin = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "btn-signin"]'), 0);

            if (!$loginInput || !$passwordInput || !$btnSignin) {
                $this->savePageToLogs($selenium);

                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            if ($remember = $selenium->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Keep me logged in")]'), 0)) {
                $remember->click();
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug('waiting for recaptcha');

            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }

            $selenium->driver->executeScript("document.getElementById('g-recaptcha-response').value='" . $captcha . "';");

            $this->savePageToLogs($selenium);
            $btnSignin->click();

            $selenium->waitForElement(WebDriverBy::xpath('//span[@id = "email-error" or @id = "password-error" or @id = "googlerecaptcha"] | //div[@id = "loginmsgcontent"] | //a[@class="btn-logout"]'), 10);
            $this->logger->debug("[Current selenium URL]: {$selenium->http->currentUrl()}");
            $this->savePageToLogs($selenium);

            foreach ($selenium->driver->manage()->getCookies() as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }

    public function LoadLoginFormTaiwan()
    {
        $this->logger->notice(__METHOD__);

        throw new CheckException("We currently do not support this region. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);

        $this->http->GetURL("https://myotgcard.starbucks.com.tw/StarbucksMemberWebsite/SignIn.html");

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'SignIn.html')]")) {
            return $this->checkErrors();
        }

        $selenium = clone $this;
        $xKeys = [];
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->useCache();

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://myotgcard.starbucks.com.tw/StarbucksMemberWebsite/SignIn.html");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "form.loginId"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "form.passw0rd"]'), 0);
            /*
            $verifyCode = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "form.verifyCode"]'), 0);
            */
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput/* || !$verifyCode*/) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            /*
            $imgVerify = $selenium->waitForElement(WebDriverBy::xpath('//img[@id ="imgVerify"]'), 0);

            if (!$imgVerify) {
                return false;
            }
            */

//            $pathToScreenshot = $selenium->takeScreenshotOfElement($imgVerify);
//
//            if (!$pathToScreenshot) {
//                $this->logger->error('Failed to get screenshot of iFrame with captcha');
//
//                return false;
//            }
//
//            $this->recognizer = $this->getCaptchaRecognizer();
//            $this->recognizer->RecognizeTimeout = 120;
//            $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot);
//            unlink($pathToScreenshot);
//
//            $verifyCode->sendKeys($captcha);
//            $verifyCode->sendKeys("dsfsdfsf");

            // Sign In
            $selenium->driver->executeScript("
                $('#loginBtn').click();
                window.stop();
            ");

            // uniqueStateKey
            if ($xKey = $selenium->waitForElement(WebDriverBy::xpath('//form[@id = "loginForm"]//input[contains(@name, "A2c3Y0FLvI-")]'), 5, false)) {
//                $selenium->driver->executeScript("
//                    window.stop();
//                ");
                foreach ($selenium->driver->findElements(WebDriverBy::xpath('//form[@id = "loginForm"]//input[contains(@name, "A2c3Y0FLvI-")]', 0, false)) as $index => $xKey) {
                    $xKeys[] = [
                        'name'  => $xKey->getAttribute("name"),
                        'value' => $xKey->getAttribute("value"),
                    ];
                }
                $this->logger->debug(var_export($xKeys, true), ["pre" => true]);
            }

//            $selenium->waitForElement(WebDriverBy::xpath("
//                //a[contains(text(), '登出')]
//                | //div[@id = 'error-summary']
//            "), 20);

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();
            $this->http->removeCookies();
            $this->logger->debug("set cookies");

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current selenium URL]: {$this->seleniumURL}");
            // save page to logs
            $this->savePageToLogs($selenium);
        } catch (TimeOutException | NoSuchDriverException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
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
                throw new CheckRetryNeededException(3, 0);
            }
        }

        if (empty($xKeys)) {
            return false;
        }
//        return true;

        foreach ($xKeys as $xKey) {
            if (isset($xKey['name'], $xKey['value'])) {
                $this->http->SetInputValue($xKey['name'], $xKey['value']);
            }
        }

        $this->http->SetInputValue('form.loginId', $this->AccountFields['Login']);
        $this->http->SetInputValue('form.passw0rd', $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('form.verifyCode', $captcha);

        return true;
    }

    public function LoadLoginFormIndia()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.starbucks.in/login");

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }
        /*
        if (!$this->http->ParseForm("loginform")) {
            return $this->checkErrors();
        }
        // from js -> handleLoginBtn()
        $this->http->Form = [];
        $headers = [
            "Accept"           => "*
        /*",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $data = [
            'username' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://rewards.starbucks.in/welcome/page', $data, $headers);
        $this->http->RetryCount = 2;
        */
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
            $selenium->useCache();

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.starbucks.in/login");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username_input"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "mat-input-1"]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Login")]'), 0);

            if (!$btn) {
                return false;
            }

            // Sign In
            $btn->click();

            if ($btnCont = $selenium->waitForElement(WebDriverBy::xpath('//button[@class = "btns btn-yes" and contains(text(), "Log out")]'), 10)) {
                $this->savePageToLogs($selenium);
                $btnCont->click();
            }

            $res = $selenium->waitForElement(WebDriverBy::xpath('//button[@class = "btns btn-log-out" and contains(text(), "Log out")] | //*[contains(@class, "mat-error")] | //span[contains(@class, "username")]'), 10);
            $this->savePageToLogs($selenium);

            if ($res && $res->getText() == 'Please enter value') {
                throw new CheckException("Please enter value", ACCOUNT_INVALID_PASSWORD);
            }

            if ($res) {
                $selenium->http->GetURL("https://www.starbucks.in/pay");
                $selenium->http->GetURL("https://www.starbucks.in/rewards");
                $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "You’ve earned")]'), 10);
            }

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();
            $this->http->removeCookies();
            $this->logger->debug("set cookies");

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->http->setCookie("user_profile", $selenium->driver->executeScript("return localStorage.getItem('user_profile');"));
            $this->http->setCookie("MSR_cards", $selenium->driver->executeScript("return localStorage.getItem('MSR_cards');"));

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current selenium URL]: {$this->seleniumURL}");
            // save page to logs
            $this->savePageToLogs($selenium);
        } catch (TimeOutException | NoSuchDriverException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
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
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }

    public function LoadLoginFormVietnam()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://card.starbucks.vn/en/sign-in");
//        $this->http->GetURL('https://card.starbucks.vn/api/XsrfToken/getToken', [
//            'Accept' => 'application/json, text/plain, */*',
//        ]);

        $captcha = $this->parseReCaptcha('6LcgmVoUAAAAAJHacqlBEnC8P4bkVO1GAyVeBJCJ', 'https://card.starbucks.vn/en/sign-in');

        if ($captcha === false) {
            return false;
        }

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json;charset=UTF-8',
        ];
        $data = [
            'keepLogin' => 'true',
            'username'  => $this->AccountFields['Login'],
            'password'  => $this->AccountFields['Pass'],
            'recaptcha' => $captcha,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://card.starbucks.vn/api/account/authenticate', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case 'China':
                // maintenance
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Dear users, please be advised that My Starbucks Rewards section of the Starbucks China website is now on maintenance')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'HongKong':
                break;

            case 'India':
                if ($this->http->FindPreg("/^The website encountered an unexpected error\. Please try again later\.$/")) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Mexico':
                // Maintenance
                if ($this->AccountFields['Login2'] == 'Mexico'
                    && $this->http->FindSingleNode("//body/div/img[contains(@src, 'QAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFA')]/@src")) {
                    throw new CheckException("Por cuestiones de mantenimiento, no tendremos disponible el programa My Starbucks Rewards del 24 at 29 de septiembre.", ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Singapore':
                // maintenance
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'In order to serve you better, this section is currently undergoing a system maintenance. We apologize for any inconvenience caused.')]")) {
                    throw new CheckException("In order to serve you better, this section is currently undergoing a system maintenance. We apologize for any inconvenience caused.", ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindSingleNode("
                        //p[contains(text(), 'We are undergoing a maintenance at the moment.')]
                        | //h1[contains(text(), 'Sorry, our website is currently undergoing maintenance.')]
                ")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Taiwan':
                // Maintenance
                if ($message = $this->http->FindSingleNode("//h2[contains(text(), '我們正在進行例行性維護作業，造成不便敬請見諒！')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Thailand':
                if ($message = $this->http->FindPreg('/Request timestamp is outside the acceptable time window./')) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindPreg('/Access Denied: Your IP address is not allowed./')) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Vietnam':
                if ($this->http->currentUrl() == 'http://www.starbucks.vn/system-upgrade.html') {
                    throw new CheckException("We are currently upgrading our system. We are sorry for the inconvenience!", ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'UK':
                break;

            case 'Switzerland':
                // Maintenance
                if ($message = $this->http->FindSingleNode("//div[
                        contains(text(), 'Apologies for any inconvenience, our site is currently not available.')
                        or contains(text(), 'Apologies for any inconvenience, we are updating the My Starbucks Rewards Program')
                    ]")
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Germany':
                break;

            case 'Canada':
                break;

            case 'USA':
            default:
        }// switch ($this->AccountFields['Login2'])

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'something has gone wrong on')]")) {
            throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'our website is out taking a coffee break')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Ocurrió un error, por favor intenta más tarde
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Ocurrió un error, por favor intenta más tarde')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (preg_match("/static\/unavailable\/section\.html/ims", $this->http->currentUrl())) {
            throw new CheckException("www.starbucks.com is currently unavailable. We are working to resolve the issue
             as quickly as possible and apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR); /*checked*/
        }
        //# The service is unavailable
        if ($message = $this->http->FindSingleNode("//body[contains(text(), 'The service is unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in '\/' Application\.)/ims")
            // We’re sorry — something has gone wrong on our end.
            || $this->http->currentUrl() == 'http://www.starbucks.com/static/error/index.html') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        if ($this->AccountFields['Login2'] == 'HongKong') {
            $this->http->PostForm();
//            $form = $this->http->Form;
//            $formURL = $this->http->FormURL;

            // Starbucks Card Account Terms of Use and Agreement    // refs #15718
//            if ($this->http->FindPreg("/\{\"d\":\{\"__type\":\"Starbucks.MSR.Lib.Manager.MongoDB.ValidateLogin\",\"valid\":true,\"locked\":false,\"chance\":\d+/ims")) {
//                $this->logger->notice("Accepting Terms of Use and Agreement...");
//                $this->http->Form = $form;
//                $this->http->FormURL = $formURL;
//                $this->http->SetInputValue('__EVENTTARGET', 'ctl00$CphMain$BtnAccept');
//                $this->http->PostForm();
//            }

            if ($this->http->FindSingleNode("//a[contains(@href, 'account/logout')]", null, true, null, 0)) {
                return true;
            }

            if ($this->http->FindPreg("/\{\"d\":false\}/ims")) {
                throw new CheckException("Invalid email or password", ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindPreg("/\{\"d\":\{\"__type\":\"Starbucks.MSR.Lib.Manager.MongoDB.ValidateLogin\",\"valid\":false,\"locked\":false,\"chance\":\d+,\"captcha\":true\}\}/ims")
                || $this->http->FindPreg("/\{\"d\":\{\"__type\":\"Starbucks.MSR.Lib.Manager.MongoDB.ValidateLogin\",\"valid\":false,\"locked\":false,\"chance\":\d+\}\}/ims")
                || $this->http->FindPreg("/\{\"d\":\{\"__type\":\"Starbucks.MSR.Lib.Manager.MongoDB.ValidateLogin\",\"valid\":false,\"locked\":false,\"chance\":\d+,\"captcha\":true,\"passwordExpired\":false\}\}/ims")
            ) {
                throw new CheckException("Invalid email or password", ACCOUNT_INVALID_PASSWORD);
            }
            // Your account is temporarily locked for 24 hours
            if (
                $this->http->FindPreg("/\{\"d\":\{\"__type\":\"Starbucks.MSR.Lib.Manager.MongoDB.ValidateLogin\",\"valid\":false,\"locked\":true,\"chance\":0\}\}/ims")
                || $this->http->FindPreg("/\{\"d\":\{\"__type\":\"Starbucks.MSR.Lib.Manager.MongoDB.ValidateLogin\",\"valid\":false,\"locked\":true,\"chance\":0,\"captcha\":true,\"passwordExpired\":false\}\}/ims")
            ) {
                throw new CheckException("Your account is temporarily locked for 24 hours", ACCOUNT_LOCKOUT);
            }

            if ($cookieMessages = $this->http->getCookieByName('mage-messages')) {
                $cookieMessages = $this->http->JsonLog(urldecode($cookieMessages));
                $message = $cookieMessages[0]->text ?? null;

                if ($message) {
                    $this->logger->error("[Error]: {$message}");

                    if ($this->http->FindPreg('/Your account will be locked for \d+ \w+ as incorrect /', false, $message)) {
                        throw new CheckException($message, ACCOUNT_LOCKOUT);
                    }

                    if ($message == "Invalid Mobile Number/Email or Password") {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }// if ($message)
            }// if ($cookieMessages = $this->http->getCookieByName('mage-messages'))
        } elseif (!$this->otherRegion || in_array($this->AccountFields['Login2'], ['Mexico', 'Peru', 'Poland', 'Taiwan', 'Japan'])) {
            $this->http->RetryCount = 0;
            $this->http->PostForm();
            $this->http->RetryCount = 2;
        } elseif ($this->AccountFields['Login2'] == 'Vietnam') {
            $response = $this->http->JsonLog();

            if (isset($response->message, $response->payload->token) && $response->message == 'Authenticated Successful.') {
                return true;
            }
            $message = $response->errors[0]->errorMessage ?? $response->message ?? null;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $message == 'Please enter a valid email address.'
                    || strstr($message, 'Please check your sign-in information and try again.')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message == 'A problem happened while handling your request. Try again later.'
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }
        } elseif ($this->AccountFields['Login2'] == 'Thailand') {
            $authResult = $this->http->JsonLog();

            if (!$authResult) {
                return $this->checkErrors();
            }

            if (
                !isset($authResult->responseCode, $authResult->token, $authResult->loyaltyMemberId, $authResult->responseMessage)
                || $authResult->responseCode !== '00'
                || $authResult->responseMessage !== 'Success'
            ) {
                $error = $authResult->responseMessage;
                $this->logger->error($error);

                if (stripos($error, 'Enter a ') !== false
                    || stripos($error, 'Invalid username or password') !== false
                    || stripos($error, 'Invalid Email or Password') !== false
                ) {
                    throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
                }

                if (stripos($error, 'Your account had been locked') !== false) {
                    throw new CheckException($error, ACCOUNT_LOCKOUT);
                }
                $this->DebugInfo = $error;

                return false;
            }

            $this->State['accessTokenThailand'] = $authResult->token;
            $this->State['memberIdThailand'] = $authResult->loyaltyMemberId;

            if ($this->loginSuccessful()) {
                return true;
            }

            return $this->checkErrors();
        } elseif ($this->AccountFields['Login2'] == 'India') {
            $response = $this->http->JsonLog();
            $responseMsg = $response->response->responseMsg ?? null;

            // Access is allowed
//            if ($responseMsg == 'All validations are passed and Request is successfully processed') {
            if ($this->http->getCookieByName("user_profile")) {
                return true;
            }

            if (in_array($responseMsg, [
                "Invalid Password ",
                "Invalid Password. Account got locked try after some time ",
                "Invalid Username",
            ])) {
                throw new CheckException(trim($responseMsg), ACCOUNT_INVALID_PASSWORD);
            }

            if (in_array($responseMsg, [
                "User account locked. Try after some time ",
            ])) {
                throw new CheckException(trim($responseMsg), ACCOUNT_LOCKOUT);
            }

            // AccountID: 4370408
            if ($this->http->FindPreg("/^\{\}$/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->AccountFields['Login2'] == 'India')
        elseif ($this->AccountFields['Login2'] == 'Singapore') {
            if ($this->http->FindSingleNode('(//a[@class="btn-logout"])[1]')) {
                return true;
            }
            $error = $this->http->FindSingleNode('//span[@id = "email-error"]')
                ?? $this->http->FindSingleNode('//span[@id = "password-error"]')
                ?? $this->http->FindSingleNode('//div[@id = "loginmsgcontent"]');

            if ($error) {
                $this->logger->error($error);

                if (stripos($error, 'Please fill in ') !== false
                    || stripos($error, 'Email address incorrect') !== false
                    || stripos($error, 'The email or password you entered is no valid') !== false
                ) {
                    throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
                }

                if (stripos($error, 'Your account had been locked') !== false) {
                    throw new CheckException($error, ACCOUNT_LOCKOUT);
                }

                if (stripos($error, 'Error F200.Something went wrong while attempting to sign you in.') !== false) {
                    throw new CheckRetryNeededException();
                }

                $this->DebugInfo = $error;

                return false;
            }

            // broken account
            if (
                $this->AccountFields['Login'] == 'jevanspritchard@gmail.com'
                && $this->http->currentUrl() == 'https://www.starbucks.com.sg/rewards/Login?returnurl=%2Frewards'
                && empty($this->http->Response['body'])
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        } else {
            if ($this->AccountFields['Login2'] == 'China') {
                $response = $this->http->JsonLog(null, 3, true);
                $error = ArrayVal($response, 'error', []);

                if (ArrayVal($error, 'message') == "Invalid username/email or password.") {
                    throw new CheckException(ArrayVal($error, 'message'), ACCOUNT_INVALID_PASSWORD);
                }
                // Access is allowed
                if ($this->http->FindPreg("/\{\}/ims")) {
                    return true;
                }
            }// if ($this->AccountFields['Login2'] == 'China')

            // Access successful
            if ($this->http->FindSingleNode("//a[contains(text(), 'Logout')]")) {
                return true;
            }

            if ($this->http->FindPreg('/"RedirectURL":"\/accountdashboard\.aspx"/ims')) {
                return true;
            }

            // Invalid credentials (Switzerland)
            if ($this->AccountFields['Login2'] == 'Switzerland') {
                if (!$this->http->PostForm()) {
                    return $this->checkErrors();
                }

                if ($this->http->FindSingleNode("//a[contains(text(), 'Logout')]")) {
                    return true;
                }

                if ($message = $this->http->FindPreg("/(?:The email or password you entered is not valid. Please try again\.|Incorrect email, password or combination\.)/")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindPreg("/(?:Unexpected error|An unexpected error just happened, please report or retry later|Sorry, we were unable to log you in\. Please try again\.)/")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            }// if ($this->AccountFields['Login2'] == 'Switzerland')

            return $this->checkErrors();
        }

        if ($this->AccountFields['Login2'] == 'Mexico') {
            $response = $this->http->JsonLog();

            if (isset($response->access_token)) {
                return true;
            }

            // No se pudo conectar con el servidor.
            if (isset($response->message) && $response->message == 'invalid_grant') {
                throw new CheckException('No se pudo conectar con el servidor.', ACCOUNT_INVALID_PASSWORD);
            }
        }// if ($this->AccountFields['Login2'] == 'Mexico')

        if ($this->AccountFields['Login2'] == 'Peru') {
            if (
                $this->http->FindSingleNode('//div[@class = "error-login text-danger"]')
                && $this->http->currentUrl() == 'https://card.starbucks.pl/auth/password-expire'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindSingleNode("//a[contains(@data-href, '/rewards/auth/logout')]")) {
                return true;
            }

            if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error-login')]/text()[last()]")) {
                $this->logger->error("[Error]: {$message}");

                if (strstr($message, 'El usuario o contraseña es incorrecto')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            }
        }

        if ($this->AccountFields['Login2'] == 'Poland') {
            if (
                $this->http->FindSingleNode('//div[@id = "noticeHeaderSuccess"]/div[contains(text(), "Your password has expired")]')
                && $this->http->currentUrl() == 'https://card.starbucks.pl/auth/password-expire'
            ) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->http->FindSingleNode("//a[contains(text(), 'Log out')]/@href")) {
                return true;
            }
            // Incorrect e-mail and / or password.
            if ($message = $this->http->FindSingleNode("//li[contains(text(),'Incorrect e-mail and / or password.')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        if ($this->AccountFields['Login2'] == 'Taiwan') {
            if ($this->http->FindNodes("//p[contains(text(), '嗨')]")
                || $this->http->FindNodes("//a[contains(text(), '登出')]")) {
                $this->captchaReporting($this->recognizer);

                return true;
            }
            // Invalid credentials
            if ($message = $this->http->FindPreg("/displayError\([^\)]+\)\s*\,\s*\"(帳戶有誤)\"/")) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Your account number or password is incorrect
            if ($message = $this->http->FindPreg("/displayError\([^\)]+\)\s*\,\s*\"(您的帳號或密碼有誤)\"/")) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // 星禮程My Starbucks Rewards™使用條款
            if ($message = $this->http->FindSingleNode("//a[@id = 'nextStep' and contains(text(), '確定')]")) {
                $this->captchaReporting($this->recognizer);

                $this->throwProfileUpdateMessageException();
            }
            // retries - wrong captcha (驗證碼不正確，請再次確認。)
            if ($message = $this->http->FindPreg("/displayError\([^\)]+\)\s*\,\s*\"(無效的驗證碼)\"/")) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
            }// if ($message = $this->http->FindSingleNode("//font[contains(text(), '驗證碼不正確，請再次確認。')]"))
//            // Login failed, please try again later
//            if ($message = $this->http->FindSingleNode("//td[contains(text(), '登入失敗，請稍後再試')]"))
//                throw new CheckException('登入失敗，請稍後再試', ACCOUNT_PROVIDER_ERROR);
        }// if ($this->AccountFields['Login2'] == 'Taiwan')

        if ($message = $this->http->FindSingleNode("//div[@class = 'validation-summary-errors']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Thailand - Username or Password incorrect!
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Username or Password incorrect!')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Thailand - Email is not valid.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Email is not valid.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Please Register A Card to Start Earning Rewards
        if ($message = $this->http->FindSingleNode("//h3[a[contains(text(), 'Register A Card')]]")) {
            throw new CheckException("Please Register Your Card to Start Earning Rewards", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'signout')]/@href", null, true, null, 0)) {
            return true;
        }

        if ($this->http->FindSingleNode("//a[@href = '/account/card/reload/onetime']/@href", null, true, null, 0)) {
            return true;
        }

        if ($this->http->FindSingleNode("//a[@href = '/account/profile']/@href", null, true, null, 0)) {
            return true;
        }

        if ($this->http->FindSingleNode("//a[@onclick = 'Logout()']/@onclick", null, true, null, 0)) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        switch ($this->AccountFields['Login2']) {
            case 'China':
                // refs #6150
                $this->ParseChina();

                break;

            case 'HongKong':
                // refs #6150
                $this->ParseHongKong();

                break;

            case 'India':
                // refs #12186
                $this->ParseIndia();

                break;

            case 'Mexico':
                // refs #7337
                $this->ParseMexico();

                break;

            case 'Peru':
                // refs #23238
                $this->ParsePeru();

                break;

            case 'Poland':
                // refs #7337
                $this->ParsePoland();

                break;

            case 'Singapore':
                $this->ParseSingapore();

                break;

            case 'Switzerland':
                // refs #5251
                $this->ParseSwitzerland();

                break;

            case 'Taiwan':
                $this->ParseTaiwan();

                break;

            case 'Thailand':
                // refs #6150
                $this->ParseThailand();

                break;

            case 'Vietnam':
                // refs #14310
                $this->ParseVietnam();

                break;

            case 'UK': case 'Germany':// refs #5567
            default:
                $this->ParseGeneral();

                break;
        }
    }

    public function ParseVietnam()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();
        $this->http->setDefaultHeader('Authorization', 'Bearer ' . $response->payload->token);
        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $this->http->GetURL('https://card.starbucks.vn/api/card/getMemberReward');
        $response = $this->http->JsonLog();

        if (!isset($response->payload->cmcEarnedPoints)) {
            return;
        }
        // Balance - Total Stars Earned
        $this->SetBalance(floor($response->payload->cmcEarnedPoints));
        // Level Member
        $this->SetProperty("EliteLevel", $response->payload->tierCode);
        // Needed Stars for Next Level
        $this->SetProperty("NeededStarsForNextLevel", ceil($response->payload->pointsToNextTier));
        // Name
        $this->http->GetURL("https://card.starbucks.vn/api/account/getMemberProfile");
        $response = $this->http->JsonLog();
        $this->SetProperty("Name", beautifulName("{$response->payload->firstName} {$response->payload->lastName}"));

        // Rewards
        $this->http->GetURL("https://card.starbucks.vn/api/voucher/getVoucherList/1");
        $response = $this->http->JsonLog();
        $vouchers = $response->payload ?? [];

        foreach ($vouchers as $voucher) {
            $this->AddSubAccount([
                'Code'           => "starbucksVietnamVoucher" . $voucher->voucherNo,
                'DisplayName'    => $voucher->name . " #" . $voucher->voucherNo,
                'Card'           => $voucher->voucherNo,
                'Balance'        => null,
                'ExpirationDate' => strtotime($this->ModifyDateFormat($voucher->validTo)),
            ]);
        }// foreach ($vouchers as $voucher)
    }

    public function ParseSingapore()
    {
        $this->logger->notice(__METHOD__);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('(//h1[contains(@class, "greeting")])[1]', null, true, "/,\s*([^<\!]+)/ims")));
        // Balance - Stars Earned
        $this->SetBalance($this->http->FindSingleNode('//div[contains(text(), "Stars for Rewards Redemption")]/preceding-sibling::div[contains(@class, "section-title--point")]'));
        // Rewards Level
        $this->SetProperty("EliteLevel", $this->http->FindSingleNode('//h2[contains(@class, "member-tier")]'));
        // Level Valid Till
//        $this->SetProperty("EliteLevelValidTill", $this->http->FindSingleNode('//div[contains(text(), "Total Stars Earned")]/following-sibling::div[contains(@class, "reward-status__figure")]/div[@class = "figure"]'));//todo: not found
        // Expiring Balance
        $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode('//div[@class = "result" and contains(., "Star(s) will be expired on")]/b[1]', null, true, "/\*\s*(.+)/"));
        $exp = $this->http->FindSingleNode('//div[@class = "result" and contains(., "Star(s) will be expired on")]/b[2]');

        if ($exp) {
            $exp = $this->ModifyDateFormat($exp);
            $this->SetExpirationDate(strtotime($exp));
        }

        $this->logger->info('Cards', ['Header' => 3]);
//        $this->http->GetURL("https://www.starbucks.com.sg/rewards/Cards");
        $headers = [
            "Accept"        => "application/json",
            "Content-Type"  => "application/json",
        ];
        $this->http->PostURL("https://www.starbucks.com.sg/rewards/api/card/search?", '{"Filter":"","PageNumber":1,"PageCount":10}', $headers);
        $response = $this->http->JsonLog();
        // collect card numbers
        $cards = $response->payload->cardLists ?? [];
        $this->logger->debug("Total " . count($cards) . " cards were found");

        foreach ($cards as $card) {
            // Card balance
            $balance = $card->cardAmount ?? null;
            // Card Number
            $number = $card->cardNo ?? null;

            if (isset($number, $balance)) {
                $this->AddSubAccount([
                    "Code"        => 'starbucksCard' . $this->AccountFields['Login2'] . $number,
                    "DisplayName" => 'Card # ' . $number,
                    "Balance"     => $balance,
                    "Card"        => $number,
                ], true);
            } else {
                $this->logger->notice("Skip bad card");
            }
        }// foreach ($cards as $card)

        $this->logger->info('My Rewards', ['Header' => 3]);
        $this->http->GetURL("https://www.starbucks.com.sg/rewards/MyRewards");
        // Rewards
        $rewards = $this->http->XPath->query('//div[@class = "rewards__item"]');
        $this->logger->debug("Total {$rewards->length} rewards found");

        for ($i = 0; $i < $rewards->length; $i++) {
            $displayName = $this->http->FindSingleNode(".//div[@class = 'title']", $rewards->item($i));
            $exp = $this->ModifyDateFormat($this->http->FindSingleNode(".//div[@class = 'expire']", $rewards->item($i), true, "/on\s*([^<]+)/ims"));
            $this->AddSubAccount([
                'Code'           => "starbucksRewards" . $i,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => strtotime($exp),
            ]);
        }// for ($i = 0; $i < $rewards->length; $i++)
    }

    /*
    public function ParseThailand()
    {
        $this->logger->notice(__METHOD__);
        // MEMBERSHIP STATUS
        $this->SetProperty("EliteLevel", $this->http->FindSingleNode('//strong[contains(text(), "Level") or contains(text(), "level")]', null, true, '/(.+)\s+Level/ims'));
        // ... Level until ...
        $this->SetProperty("EliteLevelValidTill", $this->http->FindSingleNode('//strong[contains(text(), "Level") or contains(text(), "level")]', null, true, '/Level\s*until\s*(.+)/ims'));
        // Until next Reward
        if (isset($this->Properties['EliteLevel']) && $this->Properties['EliteLevel'] == 'Gold') {
            $this->SetProperty("StarsNeeded", $this->http->FindSingleNode('//span[@class = "rewards_details--icon_container"]/strong'));
        }
        // Needed Stars for Next Level
        $this->SetProperty("NeededStarsForNextLevel", $this->http->FindSingleNode("//span[contains(text(), 'to attain')]", null, true, "/Earn (\d+) Stars? by/"));
        // Balance - Stars You've Earned
        $this->SetBalance($this->http->FindSingleNode("//span[@class = 'rewards__currentStars']/text()[1]", null, true, "/([^\/]+)/"));

        $this->http->GetURL("https://www.starbuckscardth.in.th/Profile/Rewards");
        $expNodes = $this->http->XPath->query('//h5[contains(text(), "EXPIRING STARS")]/following-sibling::div/div');
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");

        foreach ($expNodes as $expNode) {
            $date = $this->http->FindSingleNode('div[1]', $expNode);
            $expPoints = $this->http->FindSingleNode('div[2]', $expNode);

            if (
                $expPoints > 0
                && (!isset($exp) || strtotime($date) < $exp)
            ) {
                $exp = strtotime($date);
                $this->SetExpirationDate($exp);
                // The Stars will expire
                $this->SetProperty("ExpiringBalance", $expPoints);
            }
        }// foreach ($expNodes as $expNode)

        if ($this->http->ParseForm(null, "//form[contains(@action, 'Profile/Cards')]")) {
            $this->http->PostForm();
        }
        $cards = $this->http->FindNodes("//div[@class = 'owl-stage']/div/form/input[@name = 'CardSelected_CardNumber']/@value");
        $cardsCount = count($cards);
        $this->logger->debug("Total {$cardsCount} cards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($cards as $card) {
            if (!$this->http->ParseForm(null, '//form[input[@name = "CardSelected_CardNumber" and @value = "' . $card . '"]]')) {
                break;
            }
            $this->http->PostForm();
            $subAccount = [];
            // Card balance
            $subAccount["Balance"] = $this->http->FindSingleNode('//span[@id = "lbrBalance"]/parent::span');
            // Card Number
            $subAccount["Card"] = $this->http->FindSingleNode("//span[@id = 'lbrCardno0']");

            if (isset($subAccount["Card"], $subAccount["Balance"]) && $subAccount["Balance"] != '-฿') {
                $subAccount["Code"] = 'starbucksCard' . $this->AccountFields['Login2'] . $subAccount["Card"];
                $subAccount["DisplayName"] = 'Card # ' . $subAccount["Card"];
                $this->AddSubAccount($subAccount, true);
            }// if (isset($subAccount["Card"], $subAccount["Balance"]))
            else {
                $this->logger->notice("Skip bad card");
            }
        }// foreach ($cards as $card)

        // Name
        $this->http->GetURL("https://www.starbuckscardth.in.th/Profile/MyProfile");
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[@id = 'FirstName']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@id = 'LastName']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }
    */

    public function ParseThailand()
    {
        $userInfo = $this->http->JsonLog();

        // Name
        $this->SetProperty("Name", beautifulName("{$userInfo->firstName} {$userInfo->lastName}"));

        $data = [
            'LoyaltyMemberId' => $this->State['memberIdThailand'],
            'RefLang'         => 'en',
            'Timestamp'       => time(),
        ];

        $rewardLevelInfo = $this->postDataThailand('https://www.plusapiprod.com/api/CrmAuthGetRewardLevel', $data);

        // Balance - Stars You've Earned
        $this->SetBalance($rewardLevelInfo->starBalance);

        if (isset($rewardLevelInfo->rewardLevel)) {
            // MEMBERSHIP STATUS
            $this->SetProperty("EliteLevel", $rewardLevelInfo->rewardLevel);
        }

        if (isset($rewardLevelInfo->anniversaryDate)) {
            // ... Level until ...
            $this->SetProperty("EliteLevelValidTill", $rewardLevelInfo->anniversaryDate);
        }

        if (isset($rewardLevelInfo->starsToNextLevel)) {
            // Needed Stars for Next Level
            $this->SetProperty("NeededStarsForNextLevel", $rewardLevelInfo->starsToNextLevel);
        }

        if (isset($this->Properties['EliteLevel']) && $this->Properties['EliteLevel'] == 'Gold' && isset($rewardLevelInfo->starRewardBalance)) {
            $data['Timestamp'] = time();

            $bewnefitsInfo = $this->postDataThailand('https://www.plusapiprod.com/api/CrmMemberGetAllBenefit', $data);

            $rewards = $bewnefitsInfo->promotionLists->starRewards ?? [];
            $rewardsPrepared = [];

            foreach ($rewards as $reward) {
                if ($reward->availableStarRewardCount == 0) {
                    $rewardsPrepared[] = $reward;
                }
            }

            uasort($rewardsPrepared, function ($a, $b) {
                if ($a->starRewardValue == $b->starRewardValue) {
                    return 0;
                }

                return ($a->starRewardValue == $b->starRewardValue) ? -1 : 1;
            });

            if (count($rewardsPrepared) > 0) {
                // Until next Reward
                $this->SetProperty("StarsNeeded", $rewardsPrepared[0]->starRewardValue - $rewardLevelInfo->starRewardBalance);
            }
        }

        $data = [
            'UserId'    => $this->State['memberIdThailand'],
            'RefLang'   => "en",
            'Timestamp' => time(),
        ];

        $pointsExpirationData = $this->postDataThailand('https://www.plusapiprod.com/api/CrmMemberStarRewardExpiration', $data) ?? [];

        uasort($pointsExpirationData, function ($a, $b) {
            $dateA = strtotime($a->expiryDate);
            $dateB = strtotime($b->expiryDate);

            if ($dateA == $dateB) {
                return 0;
            }

            return ($dateA < $dateB) ? -1 : 1;
        });

        if (count($pointsExpirationData) > 0) {
            $this->SetExpirationDate(strtotime($pointsExpirationData[0]->expiryDate));
            // The Stars will expire
            $this->SetProperty("ExpiringBalance", $pointsExpirationData[0]->starRewardExpiration);
        }

        $data['Timestamp'] = time();

        $cardsInfo = $this->postDataThailand('https://www.plusapiprod.com/api/CrmMemberGetCardsList', $data) ?? [];

        $this->SetProperty("CombineSubAccounts", false);

        foreach ($cardsInfo as $card) {
            if (isset($card->cardLostDate)) {
                continue;
                /*
                    $this->sendNotification('refs #24523 starbucks - need to check cardLostDate // IZ');
                */
            }

            if (isset($card->cardStatusText) && !in_array($card->cardStatusText, ['Registered', 'Lost Card'])) {
                $this->sendNotification('refs #24523 starbucks - need to check cardStatusText // IZ');
            }

            $data = [
                'Cardno'    => $card->cardNumber,
                'UserId'    => $this->State['memberIdThailand'],
                'RefLang'   => "en",
                'Timestamp' => time(),
            ];

            $cardData = $this->postDataThailand('https://www.plusapiprod.com/api/CheckCardBalance', $data);

            if (!isset($cardData)) {
                continue;
            }

            $this->AddSubAccount([
                'Balance'       => $cardData->cardBalance,
                'Card'          => $cardData->cardNumber,
                'Code'          => 'starbucksCard' . $this->AccountFields['Login2'] . $cardData->cardNumber,
                'DisplayName'   => 'Card # ' . $cardData->cardNumber,
            ]);
        }
    }

    // refs #17038
    public function ParseMexico()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();
        $headers = [
            'Accept'        => '*/*',
            "Authorization" => "Bearer {$response->access_token}",
        ];
        $this->http->GetURL('https://api.starbucks.mx/v1/me/profile?ignore=tippingPreferences,devices,favoriteStores', $headers);
        $response = $this->http->JsonLog();

        if (!$response) {
            return;
        }

        $this->SetProperty('Name', beautifulName($response->user->firstName . ' ' . $response->user->lastName));
        // STARS QUE FALTAN PARA PASAR AL PRÓXIMO NIVEL
        $this->SetProperty('StarsNeeded', $response->rewardsSummary->pointsNeededForNextLevel);
        // Estás en el nivel
        $this->SetProperty('EliteLevel', $response->rewardsSummary->currentLevel);
        // Since
        $this->SetProperty('Since', date('d/m/Y', strtotime($response->rewardsSummary->cardHolderSinceDate)));

        if (isset($response->rewardsSummary->reevaluationDate)) {
            // refs 17038#note-9
            if ($exp = strtotime('+1 minute', strtotime(str_replace('T', '', preg_replace('/\.\w+$/', '', $response->rewardsSummary->reevaluationDate)), false))) {
                $this->SetExpirationDate($exp);
            }
        }

        // Balance
        if (isset($response->rewardsSummary->rewardsProgram->tierInfos)) {
            foreach ($response->rewardsSummary->rewardsProgram->tierInfos as $tierInfos) {
                if ($tierInfos->tierLevelName == $response->rewardsSummary->currentLevel) {
                    $this->SetBalance($tierInfos->tierPointsExitThreshold - $response->rewardsSummary->pointsNeededForNextLevel);

                    break;
                }
            }
        }

        // Level Gold
        if ($this->Balance == 0 && $response->rewardsSummary->currentLevel == 'Gold') {
            if ($response->rewardsSummary->pointsNeededForNextLevel == 0) {
                $this->SetBalanceNA();
            }

            if ($response->rewardsSummary->pointsNeededForNextLevel > 0) {
                $this->sendNotification('refs #17038, starbucks - pointsNeededForNextLevel > 0');
            }
        }

        $this->SetProperty("CombineSubAccounts", false);
        // Starbucks Cards
        if (isset($response->starbucksCards)) {
            $this->logger->debug("Total " . count($response->starbucksCards) . " cards were found");

            foreach ($response->starbucksCards as $card) {
                if (isset($card->cardNumber, $card->balance)) {
                    $this->AddSubAccount([
                        'Code'        => "starbucksCard{$this->AccountFields['Login2']}{$card->cardNumber}",
                        'DisplayName' => "Card #{$card->cardNumber}",
                        'Card'        => $card->cardNumber,
                        'Balance'     => $card->balance,
                    ]);
                }
            }
        }

        if (isset($response->rewardsSummary->coupons)) {
            $this->logger->debug("Total " . count($response->rewardsSummary->coupons) . " coupons were found");
            $i = 0;

            foreach ($response->rewardsSummary->coupons as $coupon) {
                if (isset($coupon->name, $coupon->expirationDate)) {
                    $i++;
                    $this->AddSubAccount([
                        'Code'           => "starbucksCoupon{$this->AccountFields['Login2']}{$i}",
                        'DisplayName'    => "Coupon {$coupon->name}",
                        'Balance'        => null,
                        'ExpirationDate' => strtotime(str_replace('T', '', preg_replace('/\.\w+$/', '', $coupon->expirationDate)), false),
                    ]);
                }
            }
        }
    }

    // refs #23238
    public function ParsePeru()
    {
        $this->logger->notice(__METHOD__);

        // Balance
        $this->SetBalance($this->http->FindSingleNode("//div[@class='number-points']/div[@class='points-value']"));

        $this->http->GetURL('https://www.starbucks.pe/rewards/account/profile-user');
        // Name
        $name = $this->http->FindSingleNode("//input[@name='FirstName']/@value") . ' ' . $this->http->FindSingleNode("//input[@name='LastName']/@value");
        $this->SetProperty("Name", beautifulName($name));

        // Cards
        $this->http->GetURL('https://www.starbucks.pe/rewards/card/index');
        $rewards = $this->http->XPath->query("//div[contains(@class,'card-default-image-container')]");
        $this->logger->debug("Total {$rewards->length} Cards were found");
        $this->SetProperty("CombineSubAccounts", false);

        for ($i = 0; $i < $rewards->length; $i++) {
            $card = $this->http->FindSingleNode(".//div[contains(@class,'main-card-balance')]", $rewards->item($i));
            $balance = $this->http->FindSingleNode(".//h2[contains(@class,'card-amount starbucks-amount')]", $rewards->item($i), false, '#S/\s*([\d.,]+)#');
            $this->AddSubAccount([
                'Code'           => "starbucksCard" . $this->AccountFields['Login2'] . str_replace([' ', '•'], '', $card),
                'DisplayName'    => 'Card #' . $card,
                'Balance'        => $balance,
            ]);
        }
    }

    // refs #17278
    public function ParsePoland()
    {
        $this->logger->notice(__METHOD__);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//img[@class='profileAvatar']/@alt")));
        // Balance
        $this->SetBalance($this->http->FindSingleNode("//div[@class='starsCount starsCountMain']"));
        // Status
        $this->SetProperty("EliteLevel", $this->http->FindPreg('#<div class="title">([^>]+?)\s+Status</div>#sim'));
        // Earn 49 Stars to get a Reward
        $this->SetProperty("StarsNeeded", $this->http->FindSingleNode("//span[contains(text(),'Stars to get a Reward')]", null, false, '/Earn (\d+) /'));

        // Cards
        $this->http->GetURL("https://card.starbucks.pl/cards/card-info");
        $rewards = $this->http->XPath->query("//section[@id='cardInfoContainer']");
        $this->logger->debug("Total {$rewards->length} Cards were found");
        $this->SetProperty("CombineSubAccounts", false);

        for ($i = 0; $i < $rewards->length; $i++) {
            $card = $this->http->FindSingleNode(".//h3[contains(text(),'Starbucks Card number:')]/following-sibling::p", $rewards->item($i));
            $balance = $this->http->FindSingleNode(".//div[@class='balance']/span[@class='amount']", $rewards->item($i));
            $expDate = $this->http->FindSingleNode(".//span[contains(text(),'Expires at:')]/following-sibling::span", $rewards->item($i));
            $this->AddSubAccount([
                'Code'           => "starbucksCard" . $this->AccountFields['Login2'] . str_replace([' ', '•'], '', $card),
                'DisplayName'    => 'Card #' . $card,
                'Balance'        => $balance,
                'ExpirationDate' => strtotime($expDate, false),
            ]);
        }
    }

    public function ParseIndia()
    {
        $this->logger->notice(__METHOD__);
        /*
        $this->http->PostURL("https://rewards.starbucks.in/welcome/getstarsearned", "");
        $response = $this->http->JsonLog($this->http->JsonLog(null, 0));

        if (isset($response->message) && $response->message == "Invalid access token") {
            throw new CheckRetryNeededException(3, 0);
        }
        */
        $response = $this->http->JsonLog($this->http->getCookieByName("user_profile"));

        // Name
//        $this->SetProperty("Name", beautifulName(($response->detail->firstName ?? null) . " " . ($response->detail->lastName ?? null)));
        $this->SetProperty("Name", beautifulName(($response->firstName ?? null) . " " . ($response->lastName ?? null)));
        // Balance - You're earning
        $this->SetBalance($response->detail->starBalance ?? $this->http->FindSingleNode('//div[@class = "points"]/h2'));
        // Needed Stars for Next Level
//        $this->SetProperty("NeededStarsForNextLevel", $this->http->FindSingleNode("//div[contains(text(), 'Stars until') and contains(text(), 'evel')]", null, true, "/(\d+)\s*Star/"));//todo
        // Cardholder Since
//        $this->SetProperty("Since", );//todo
        // Rewards Level
        $this->SetProperty("EliteLevel", $response->detail->tierLevel ?? $this->http->FindSingleNode('//h6[contains(text(), "You are on the")]/following-sibling::h2'));
        // Level expires
        $this->SetProperty("EliteLevelValidTill", $response->detail->anniversaryDate ?? $this->http->FindSingleNode('//div[@class = "status"]/following-sibling::div[@class = "date"]'));

        // Account Rewards
        $this->logger->info("Rewards", ['Header' => 3]);
        $rewards = $this->http->XPath->query('//div[@class = "swiper-wrapper"]/div');
        $this->logger->debug("Total Rewards found: {$rewards->length}");

        foreach ($rewards as $reward) {
            $displayName = $this->http->FindSingleNode('.//h3[contains(@class, "card-title")]', $reward);
            $expirationDate = $this->ModifyDateFormat($this->http->FindSingleNode('.//h6[contains(text(), "EXPIRES ON")]', $reward, true, "/ON\s*(.+)/"));
            $this->logger->debug("$displayName -> {$expirationDate}");

            if ($displayName && $expirationDate) {
                $this->AddSubAccount([
                    'Code'           => "starbucksRewardsIndia" . md5($displayName) . strtotime($expirationDate),
                    'DisplayName'    => $displayName,
                    'Balance'        => null,
                    'ExpirationDate' => strtotime($expirationDate),
                ]);
            }
        }// for ($i = 0; $i < $rewards->length; $i++)

        $this->logger->info("Cards", ['Header' => 3]);
        $cards = $this->http->JsonLog($this->http->getCookieByName("MSR_cards")) ?? [];
        $this->logger->debug("Total " . count($cards) . " cards were found");

        foreach ($cards as $card) {
            $displayName = $card->shortCardNumber;
            $balance = $card->balance;

//            if (!stristr($active, 'Deactivated')*/)
            $this->AddSubAccount([
                'Code'        => "starbucksCardIndia" . str_replace(' ', '', $displayName),
                'DisplayName' => "Card # " . $displayName,
                'Balance'     => $balance,
                'Card'        => str_replace([' ', '.'], '', $displayName),
            ]);

            break;
        }// foreach ($response->detail->cardList as $card)

        return;

        // Level and Rewards
//        $this->http->GetURL("https://rewards.starbucks.in/rewards");
        // rewardId
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://rewards.starbucks.in/getRewardDeatails", "");
        $this->http->RetryCount = 2;
        $rewardDetails = $this->http->JsonLog($this->http->JsonLog(null, 0), 0);

        $cache = Cache::getInstance();

        // it helps
        if (!empty($rewardDetails)) {
            $this->logger->notice("save rewardDetails to cache");
            $cache->set("starbucks_India_rewardDetails", $rewardDetails, 3600 * 48);
        } else {
            $this->logger->notice("get rewardDetails from cache");
            $this->SetBalanceNA();
            $rewardDetails = $cache->get("starbucks_India_rewardDetails");
        }

        $this->SetProperty("CombineSubAccounts", false);

//        $this->http->PostURL("https://rewards.starbucks.in/welcome/rewardhistory", "");
//        $response = $this->http->JsonLog($this->http->JsonLog(null, 0));
//        // Account Rewards
//        $rewards = $response->detail->rewardAvailableList ?? [];
//        $this->logger->debug("Total Rewards found: ".count($rewards));
//        foreach ($rewards as $reward) {
//            $rewardId = $reward->rewardId;
//            $displayName = null;
//            foreach ($rewardDetails as $rewardDetail) {
//                if ($rewardId === $rewardDetail->rewardid) {
//                    $displayName = $rewardDetail->rewardtitle;
//                    break;
//                }
//            }
//            $expirationDate = $reward->expiryDate;
//
//            if ($displayName && $expirationDate)
//                $this->AddSubAccount([
//                    'Code' => "starbucksRewardsIndia".$rewardId.strtotime($expirationDate),
//                    'DisplayName' => $displayName,
//                    'Balance' => null,
//                    'ExpirationDate' => strtotime($expirationDate),
//                ]);
//        }// for ($i = 0; $i < $rewards->length; $i++)

        // cards
//        $this->http->GetURL("https://rewards.starbucks.in/payment-successful");
        $this->http->PostURL("https://rewards.starbucks.in/welcome/getcardlist", "");
        $response = $this->http->JsonLog($this->http->JsonLog(null, 0));

        $this->logger->info("Rewards", ['Header' => 3]);
        // Account Rewards
        $rewards = $response->detail->rewardList ?? [];
        $this->logger->debug("Total Rewards found: " . count($rewards));

        foreach ($rewards as $i => $reward) {
            $rewardId = $reward->offerCode;
            $displayName = null;

            foreach ($rewardDetails->rewdatavail as $rewardDetail) {
                if ($rewardId === $rewardDetail->rewardid) {
                    $displayName = $rewardDetail->rewardtitle;

                    break;
                }
            }
            $expirationDate = $this->ModifyDateFormat(str_replace(' 00:00:00', '', $reward->offerExpiryDate));

            if ($displayName && $expirationDate) {
                $this->AddSubAccount([
                    'Code'           => "starbucksRewardsIndia" . $i . $rewardId . strtotime($expirationDate),
                    'DisplayName'    => $displayName,
                    'Balance'        => null,
                    'ExpirationDate' => strtotime($expirationDate),
                ]);
            }
        }// for ($i = 0; $i < $rewards->length; $i++)

        $this->logger->info("Cards", ['Header' => 3]);
        $cards = $response->detail->cardList ?? [];
        $this->logger->debug("Total " . count($cards) . " cards were found");

        foreach ($cards as $card) {
            $displayName = $card->cardNumber;
//            $active = $card->cardType;
            $balance = $card->cardBalance;

//            if (!stristr($active, 'Deactivated')*/)
            $this->AddSubAccount([
                'Code'        => "starbucksCardIndia" . str_replace(' ', '', $displayName),
                'DisplayName' => "Card # " . $displayName,
                'Balance'     => $balance,
                'Card'        => str_replace([' ', '.'], '', $displayName),
            ]);

            break;
        }// foreach ($response->detail->cardList as $card)
    }

    public function ParseHongKong()
    {
        $this->logger->notice(__METHOD__);
        // Name
        $this->http->GetURL('https://www.starbucks.com.hk/en/customer/account/profile/');
        $name = $this->http->FindSingleNode('//div[contains(@class, "sbProfile-perInfoDetails")][1]/div[1]');

        if (empty($name)) {
            $name = $this->http->FindSingleNode('//div[contains(@class, "sbProfile-perInfoDetails")][1]/div[2]');
        }
        $this->SetProperty("Name", beautifulName($name));

        $this->http->GetURL("https://www.starbucks.com.hk/en/card/account/mainpage");
        $cards = $this->http->JsonLog($this->http->FindPreg('/MxStarbucks_Card\/js\/cmp-function": \{\W+"data":(\[\{.+}])\s+},/'));
        $cardsCount = count($cards);
        $this->logger->debug("Total {$cardsCount} cards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($cards as $card) {
            $cardNumber = $card->card_number ?? null;
            $lastFourDigits = substr($cardNumber, -4);
            $cardName = $card->card_name ?? null;
            $this->AddSubAccount([
                'Code'        => 'starbucksCard' . $this->AccountFields['Login2'] . $cardNumber,
                'DisplayName' => "$cardName ($lastFourDigits)",
                'Balance'     => $card->balance ?? null,
                'Card'        => $cardNumber,
            ]);
        }// foreach ($cards as $card)

        $this->logger->info("Properties", ['Header' => 3]);
        $this->http->GetURL("https://www.starbucks.com.hk/en/rewards/account/homepage/");
        //# Balance - stars earned
        $this->SetBalance($this->http->FindSingleNode("//div[@class = 'rewards-homeCircle-nowScore']/div"));
        // Member Since
        $this->SetProperty("Since", $this->http->FindSingleNode("//div[@class = 'rewards-homeTop-memberSince']", null, true, "/Since (\d\d \w{3} \d{4})/"));
        // Rewards Level
        $level = $this->http->FindSingleNode("//div[@class = 'rewards-topRight-score']/span");
        $this->SetProperty("EliteLevel", $level);

        if ($level !== 'Gold') {
            // Next Level
            $this->SetProperty("NextLevel", $this->http->FindSingleNode("//div[starts-with(@class, 'rewards-topRight-target')]/span", null, true, "/to unlock (\w+) level/"));
            // Needed Stars for Next Level
            $this->SetProperty("NeededStarsForNextLevel", $this->http->FindSingleNode("//div[starts-with(@class, 'rewards-topRight-target')]/div"));
            // Level expires
            $this->SetProperty("EliteLevelValidTill", $this->http->FindSingleNode("//div[starts-with(@class, 'rewards-topRight-target')]/span", null, true, "/level by (\d\d \w{3} \d{4})/"));
        } else { // GOLD LEVEL FEATURES
            // Earn (some) Stars by (date) to extend Gold level For 1 year
            $this->SetProperty('StarsToRenewGoldLevel', $this->http->FindSingleNode("//div[@class = 'gold-level-earnText']", null, true, "/Earn (\d+) Stars by \d\d \w{3} \d{4} to extend Gold level/"));
            // Level expires
            $this->SetProperty("EliteLevelValidTill", $this->http->FindSingleNode("//div[@class = 'gold-level-earnText']", null, true, "/Earn \d+ Stars by (\d\d \w{3} \d{4}) to extend Gold level/"));
            // (stars) to a Reward
            $this->SetProperty("StarsNeeded", $this->http->FindSingleNode("//div[starts-with(@class, 'rewards-topRight-target')]/div"));
            // Rewards
            $this->http->GetURL("https://www.starbucks.com.hk/en/rewards/account/rewardslist/");
            $rewards = $this->http->XPath->query("//div[starts-with(@class, 'myRewards-listDetails ')]");
            $this->logger->debug("Total {$rewards->length} Rewards were found");

            for ($i = 0; $i < $rewards->length; $i++) {
                $id = $this->http->FindSingleNode("div/div[@class = 'myRewards-details-share']/a/@href", $rewards->item($i), true, "/rid\/(\d+)/");
                $displayName = $this->http->FindSingleNode("div/p[@class = 'rewards-myRewards-listTitle']", $rewards->item($i));
                $expirationDate = $this->http->FindSingleNode("div/div[@class = 'myRewards-details-share']/span", $rewards->item($i), true, "/Expires ([\w ]+)/");
                $subAccount = [
                    'Code'           => "starbucksReward" . $this->AccountFields['Login2'] . $id,
                    'DisplayName'    => $displayName,
                    'Balance'        => null,
                ];

                if (!empty($expirationDate) && strtotime($expirationDate)) {
                    $subAccount['ExpirationDate'] = strtotime($expirationDate);
                }
                $this->AddSubAccount($subAccount);
            }
        }
        /*
        // Balance - Stars  // https://redmine.awardwallet.com/issues/15718#note-5
        if (isset($this->Properties['EliteLevel'], $this->Properties['NeededStarsForNextLevel'])) {
            switch ($this->Properties['EliteLevel']) {
                case 'WELCOME':
                    $balance = 25 - $this->Properties['NeededStarsForNextLevel'];
                    $this->SetBalance($balance);

                    break;

                case 'GREEN LEVEL':
                case 'GOLD LEVEL':
                    $balance = 100 - $this->Properties['NeededStarsForNextLevel'];
                    $this->SetBalance($balance);

                    break;
            }// switch ($this->Properties['EliteLevel'])
        }// if (isset($this->Properties['EliteLevel'], $this->Properties['NeededStarsForNextLevel']))
        // Life Gold Card member - no balance // refs #15718
        elseif (isset($this->Properties['EliteLevel']) && $this->Properties['EliteLevel'] == 'GOLD LEVEL'
            && $this->http->FindSingleNode("//div[@id = 'CphMain_PnlGoodUntil']/p[contains(., 'Life Gold Card member')]")
            && $this->http->FindSingleNode("//div[@class = 'star-earned' and normalize-space(text()) = 'No need to earn Stars to extend your Gold Level']")) {
            $this->SetBalanceNA();
        }
        */
    }

    public function ParseChina()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://profile.starbucks.com.cn/api/Customers/detail");
        $response = $this->http->JsonLog();
        // Name
        if (isset($response->firstName, $response->lastName)) {
            $this->SetProperty("Name", $response->firstName . " " . $response->lastName);
        }
        // Balance - Stars
        if (isset($response->loyaltyPoints)) {
            $this->SetBalance($response->loyaltyPoints);
        }
        //# Cardholder Since
        if (isset($response->since)) {
            $this->SetProperty("Since", $response->since);
        }
        // Level
        if (isset($response->loyaltyTier)) {
            $this->SetProperty("EliteLevel", $response->loyaltyTier);
        }

        if (isset($this->Properties['EliteLevel']) && strtolower($this->Properties['EliteLevel']) != "gold") {
            //# Needed Stars for Next Level
            if (isset($response->b10G1purchasesNeeded)) {
                $this->SetProperty("NeededStarsForNextLevel", $response->b10G1purchasesNeeded);
            }
        } else {
            // Stars Until Your Next Free Drink
            if (isset($response->b10G1purchasesNeeded)) {
                $this->SetProperty("StarsNeeded", $response->b10G1purchasesNeeded);
            }
        }

        // Sub Accounts - Rewards // refs #6150
        $this->http->GetURL("https://profile.starbucks.com.cn/api/Customers/rewards?status=Unused&pageNum=1&pageSize=50");
        $response = $this->http->JsonLog(null, true, true);
        $this->logger->debug("Total rewards found: " . count($response));

        if (is_array($response)) {
            foreach ($response as $reward) {
                $status = ArrayVal($reward, 'status');
                $displayName = ArrayVal($reward, 'description');
                $exp = ArrayVal($reward, 'expiryDate');
                $balance = ArrayVal($reward, 'quantity');
                $memberBenefitId = ArrayVal($reward, 'memberBenefitId');

                if (isset($status, $displayName, $exp, $balance) && strtolower($status) == 'available') {
                    $subAccounts[] = [
                        'Code'           => "starbucksFreeDrinks" . $memberBenefitId,
                        'DisplayName'    => $displayName,
                        'Balance'        => $balance,
                        'ExpirationDate' => strtotime($exp),
                    ];
                }// if (isset($status, $displayName, $exp, $balance) && strtolower($status) == 'available')
            }
        }// foreach ($response as $reward)

        // cards
        $this->http->GetURL("https://profile.starbucks.com.cn/api/Customers/cards/list");
        $response = $this->http->JsonLog(null, true, true);

        if (is_array($response)) {
            foreach ($response as $card) {
                if (ArrayVal($card, 'cardStatus') == 'Registered') {
                    $cardNumber = ArrayVal($card, 'cardNumber');
                    $subAccount = [
                        "Code"        => 'starbucksCard' . $this->AccountFields['Login2'] . $cardNumber,
                        "DisplayName" => 'Card # ' . $cardNumber,
                        "Card"        => $cardNumber,
                        "Balance"     => null,
                    ];
                    $subAccounts[] = $subAccount;
                }//if (ArrayVal($card, 'cardStatus') == 'Registered')
            }
        }// foreach ($response as $card)

        if (isset($subAccounts)) {
            $this->SetProperty("CombineSubAccounts", false);
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }
    }

    public function ParseSwitzerland()
    {
        $this->logger->notice(__METHOD__);

        $this->ParseGeneral();
    }

    public function ParseGeneral()
    {
        $this->logger->notice(__METHOD__);

        $this->http->GetURL("https://www.starbucks." . $this->domain . "/account/rewards/my-rewards");
        // Balance - Earned stars
        $this->SetBalance($this->http->FindSingleNode("//span[@class = 'balance-text']"));
        // Level
        // refs #24629#note-2
//        if ($this->http->FindSingleNode('//div[@class = "progress-stars-description"]/text()[last()]', null, true, "/until Gold level/")) {
//            $this->SetProperty("EliteLevel", 'Green');
//        } elseif ($this->http->FindSingleNode('//div[@class = \'progress-stars-deadline\' and not(contains(text(), \'to earn\'))]', null, true, "/to stay gold/")) {
//            $this->SetProperty("EliteLevel", 'Gold');
//        }
//        $this->SetProperty("EliteLevel", $this->browser->FindSingleNode("//a[contains(text(), 'My Rewards Level:')]", null, true, "/:\s*([^<]+)/"));
        // Stars until Gold Level
        $this->SetProperty("NeededStarsForNextLevel", $this->http->FindPreg("/Earn (\d+) Stars? by \d+ [A-Z]+ to go to [A-Z]+/ims"));
        // Earn \d+ Stars by <date> to stay <level>
        // Sammle \d+ Sterne bis zum <date> to stay <level>
        $this->SetProperty("EliteLevelValidTill", $this->http->FindSingleNode("//div[@class = 'progress-stars-deadline' and not(contains(text(), 'to earn'))]", null, true, "/(?:by|zum)\s*([\/\d]+ [A-Za-z]+)/"));

        $this->http->GetURL("https://www.starbucks." . $this->domain . "/account/personal");
        // Name
        $this->SetProperty("Name", beautifulName(
                $this->http->FindSingleNode("//input[@name = 'firstName']/@value") . " " . $this->http->FindSingleNode("//input[@name = 'lastName']/@value"))
        );

        $this->parseCardsEurope();
    }

    public function ParseTaiwan()
    {
        $this->logger->notice(__METHOD__);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[contains(text(), '嗨')]", null, true, "/嗨，(.+)/")));

        $memberAjaxURL = $this->http->FindPreg("/ajax\.send\(\'([^\']+)\'\,\s*\{\"action\":\"starMemInfo\"\}\);/ims");

        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];

        if ($memberAjaxURL) {
            $this->http->NormalizeURL($memberAjaxURL);
            $this->http->PostURL($memberAjaxURL, ["action" => "starMemInfo"], $headers);
        }

        $response = $this->http->JsonLog(null, 3, true);
        // Balance - 目前已累積的星星數
        if (strstr(ArrayVal($response['data'], 'membershipLevel'), 'Gold')) {
            $this->SetBalance(ArrayVal($response['data'], 'currentStarsForGold35'));
        } else {
            $this->SetBalance(ArrayVal($response['data'], 'balanceStars'));
        }
        // Needed Stars for Next Level
        $this->SetProperty("NeededStarsForNextLevel", ArrayVal($response['data'], 'starsToNextLevel'));
        // Level
        $level = ArrayVal($response['data'], 'membershipLevel');
        $this->SetProperty("EliteLevel", str_replace(' Level', '', $level));

        // Rewards

        if ($memberAjaxURL) {
            $this->http->PostURL($memberAjaxURL, ["action" => "reward"], $headers);
            $response = $this->http->JsonLog(null, 3, true);
            $rewards = ArrayVal($response, 'data', []);
            $rewardsCount = count($rewards);
            $this->logger->debug("Total {$rewardsCount} rewards were found");
            $i = 0;

            foreach ($rewards as $reward) {
                // 使用代碼 - Promotion Code
                $code = ArrayVal($reward, 'PromotionCode');
                $isRedeemed = $reward['IsRedeemed'];
                $displayName = ArrayVal($reward, 'Name');
                // 有效日期至
                $exp = ArrayVal($reward, 'ExpiresOn');
                $this->http->Log("$displayName #$code ($exp) -> $isRedeemed");

                if (isset($code, $displayName) && !$isRedeemed) {
                    $this->AddSubAccount([
                        'Code'           => "starbucksTaiwanRewards" . $i . $code,
                        'DisplayName'    => $displayName,
                        'Balance'        => null,
                        'Card'           => $code,
                        'ExpirationDate' => strtotime($exp),
                    ]);
                    $i++;
                }// if (isset($code, $displayName) && $isRedeemed != false)
            }// foreach ($cards as $card)
        }// if ($memberAjaxURL)

        // Cards

        $this->http->GetURL("https://myotgcard.starbucks.com.tw/StarbucksMemberWebsite/Reload.html");
        $cards = $this->http->FindNodes("//ul[@class = 'card-container']/li/a/@id");
        $cardsCount = count($cards);
        $this->logger->debug("Total {$cardsCount} cards were found");

        foreach ($cards as $card) {
            $this->logger->debug("Loading card {$card}...");
            // set card
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://myotgcard.starbucks.com.tw/StarbucksMemberWebsite/SetCurrentCardNo.ajax",
                ["cardNo" => $card], $headers);
            $this->http->RetryCount = 2;
            // get Balance
            $this->http->PostURL("https://myotgcard.starbucks.com.tw/StarbucksMemberWebsite/GetSvcQueryCard.ajax",
                ["form.cardNo" => $card], $headers);
            $response = $this->http->JsonLog(null, 3, true);
            // 隨行卡號 - Card Number
            $number = ArrayVal($response, 'cardNoFmt');
            // 紅利點數 - Bonus Points
            $balance = ArrayVal($response, 'bonus');
            // 目前餘額 - Balance
            $cardBalance = ArrayVal($response, 'balance');

            if (isset($number, $balance)) {
                $this->AddSubAccount([
                    'Code'        => "starbucksTaiwan" . $number,
                    'DisplayName' => "Card # " . $number,
                    'Balance'     => $balance,
                    'Card'        => $number,
                    'CardBalance' => isset($cardBalance) ? "NT$ " . $cardBalance : null,
                ]);
            }
        }// foreach ($cards as $card)
    }

    protected function parseHCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);
        $key = $key ?? $this->http->FindSingleNode('//div[contains(@class, "h-captcha")]/@data-sitekey');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => "hcaptcha",
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'USA';
        }

        return $region;
    }

    protected function parseReCaptcha($key = null, $currentUrl = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");
        }

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $currentUrl ?? $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case 'Taiwan':
                $file = $this->http->DownloadFile("https://myotgcard.starbucks.com.tw/StarbucksMemberWebsite/common/RandomVerifyNumber?d=1", "jpg");

                break;

            case 'HongKong':
                $file = $this->http->DownloadFile("https://card.starbucks.com.hk/Captcha.ashx?" . date("UB"), "png");

                break;

            default:
                return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = str_replace(' ', '', $this->recognizeCaptcha($this->recognizer, $file));
        unlink($file);

        return $captcha;
    }

    private function parseCardsEurope()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.starbucks." . $this->domain . "/account/cards");

        $cards = $this->http->FindNodes('//a[@class = "card"]/@href');
        $cardsCount = count($cards);
        $this->logger->debug("Total {$cardsCount} cards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($cards as $card) {
            $subAccount = [];
            $this->logger->notice("Loading card {$card}...");
            $this->http->NormalizeURL($card);
            $this->http->GetURL($card);
            $this->increaseTimeLimit();
            // Card Name
            $cardName = $this->http->FindSingleNode('//div[@data-endpoint-namespace = "nicknameCard"]//input/@value');
            $balanceURL = $this->http->FindSingleNode('//div[@data-component = "card-balance"]/@data-endpoint-path');

            if (!$balanceURL) {
                $this->sendNotification("Card balance not found");

                continue;
            }

            $this->http->NormalizeURL($balanceURL);
            $this->http->GetURL($balanceURL);
            $response = $this->http->JsonLog();

            if (!isset($response->balance)) {
                $this->logger->notice("provider bug fix");
                sleep(5);
                $this->http->NormalizeURL($balanceURL);
                $this->http->GetURL($balanceURL);
                $response = $this->http->JsonLog();
            }

            // Card balance
            $subAccount["Balance"] = $response->balance;
            // Card Number
            $subAccount["Card"] = $response->cardNumber;

            if (!isset($cardName) && isset($subAccount["Card"])) {
                $cardName = "Card # {$subAccount["Card"]}";
            }

            if ($cardName) {
                $subAccount["Code"] = 'starbucksCard' . $this->AccountFields['Login2'] . $subAccount["Card"];
                $subAccount["DisplayName"] = $cardName;
            }

            $this->AddSubAccount($subAccount);
        }// foreach ($cards as $card)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case 'Peru':
                $this->http->RetryCount = 0;
                $this->http->GetURL(self::REWARDS_PAGE_URL_PERU);
                $this->http->RetryCount = 2;

                if (
                    $this->http->FindNodes("//a[contains(@data-href, '/rewards/auth/logout')]")
                ) {
                    return true;
                }

                break;

            case 'Singapore':
                $this->http->RetryCount = 0;
                $this->http->GetURL(self::REWARDS_PAGE_URL_SINGAPORE);
                $this->http->RetryCount = 2;

                if (
                    $this->http->FindNodes('//a[contains(@href, "Logout")]')
                ) {
                    return true;
                }

                break;

            case 'Thailand':
                if (!isset($this->State['memberIdThailand'], $this->State['accessTokenThailand'])) {
                    return false;
                }

                $data = [
                    'UserId'    => $this->State['memberIdThailand'],
                    'RefLang'   => 'en',
                    'Timestamp' => time(),
                ];

                $this->http->RetryCount = 0;
                $userInfo = $this->postDataThailand('https://www.plusapiprod.com/api/CrmMemberGetInfo', $data);
                $this->http->RetryCount = 2;

                if (!isset($userInfo)) {
                    return false;
                }

                if (
                    isset($userInfo->email)
                    || $userInfo->email === $this->AccountFields['Login']
                ) {
                    return true;
                }

                break;
        }

        return false;
    }

    private function postDataThailand($url, $dataRaw)
    {
        $headers = [
            'Accept'        => '*/*',
            'Content-Type'  => 'application/json',
        ];

        if (isset($this->State['accessTokenThailand'])) {
            $headers['Authorization'] = 'Bearer ' . $this->State['accessTokenThailand'];
        }

        $data = [
            'Data' => base64_encode(openssl_encrypt(json_encode($dataRaw), 'AES-256-CBC', base64_decode(self::CRYPTO_KEY_BASE64_THAILAND), OPENSSL_RAW_DATA, self::CRYPTO_IV_THAILAND)),
        ];

        $this->logger->debug('DATA: ' . print_r($dataRaw, true));

        $this->logger->debug('DATA ENCRYPTED: ' . print_r($data, true));

        $this->http->PostURL($url, json_encode($data), $headers);

        return $this->http->JsonLog();
    }
}
