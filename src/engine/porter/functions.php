<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerPorter extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const REWARDS_PAGE_URL = "https://www.flyporter.com/en-us/viporter/dashboard";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $disablePostForm = false;

    private $brokenAccounts = [
        "ctudhope@tudhope.com",
        "gord.eby@gmail.com",
        "flyporter@daviesbros.ca",
        "maldoff@hotmail.com",
        "ahouse12@rogers.com",
        "bstanley@nbnet.nb.ca",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setMaxRedirects(7);
        /*
        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
        */
//        $this->http->setRandomUserAgent(10, false, true, true, false, false);//todo
        /*
        $this->http->setUserAgent(HttpBrowser::PROXY_USER_AGENT);
        */
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;
        /*
        if ($this->humanVerify()) {
        }
        */
        if ($this->loginSuccessful()) {
            return true;
        }
        sleep(5);

        return false;
    }

    public function LoadLoginForm()
    {
//        $this->http->removeCookies();
        // remove this line ?
//        $this->http->setCookie('_px3', 'adf78efedff461805c798c34c44c9b5670921611d58cd236b5871a2480209f3b:RiK12I7/G04WBWtwMvNTwMijPwbxYpY4A8pRnK7Au/GVxhr75zaXJOut3zGhk3xB6KkEROYY2jsRnYkYLAaz6Q==:1000:MF/6lddmZmT6EqLUVfMKqdhHxq2IrQO1dnAnNTEysxxsV82r2FnqGJEZqaItt06s/yxajngnyT39LkCpJhY9F6W/Xu3ZUcVIkj5b0Us/LDhJGmOJDg2hXd0Ge7yv9CtgFts8UsvC33MURMoKgNYRzapEnPTJ5MEf2eHy+YZ11i4=', 'www.flyporter.com', '/');
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.flyporter.com/viporter/account-summary?culture=en-US");
        $this->http->RetryCount = 2;

        $this->selenium();
        $this->disablePostForm = true;

        return true;

        // recaptcha workaround
        if ($this->humanVerify()) {
            /*
            $this->disablePostForm = true;
            return true;
            */
        }

        if (!$this->http->ParseForm('formLogin')) {
            if (
                $this->http->FindPreg('/^https:\/\/www\.flyporter\.com\/\w{2}(?:\-\w{2}|)\/viporter\/points\-history$/', false, $this->http->currentUrl())
            ) {
                $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);

                if ($this->loginSuccessful()) {
                    $this->Parse();

                    return false;
                }
            }

            if (!$this->http->ParseForm('formLogin')) {// provider bug fix, isLoggedIn issue
                return $this->checkErrors();
            }
        }
        $this->AccountFields['Login'] = str_ireplace(" ", "", $this->AccountFields['Login']);
        $this->http->SetInputValue('VIPorterNumberOrEmailOrUsername', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('RememberMe', "true");
        $this->http->SetInputValue('LoginButton', "Sign In");

        return true;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $logout = false;
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useChromePuppeteer();
            $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;

            $selenium->setKeepProfile(true);
            $selenium->disableImages();
            $selenium->useCache(); //todo
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.flyporter.com/viporter/account-summary?culture=en-US');
            $selenium->http->GetURL('https://www.flyporter.com/en-us/login?ReturnUrl=%2Fen-us%2Fviporter%2Faccount-summary');

            $key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3);
            // save page to logs
            $this->savePageToLogs($selenium);

            if ($this->clickCloudFlareCheckboxByMouse($selenium)) {
                $selenium->waitForElement(WebDriverBy::xpath("//div[@id and @style=\"display: grid;\"] | //div[@id=\"ulp-auth0-v2-captcha\"] | //div[@id=\"cf-turnstile\"] | //input[@id = 'txtVIPorterUsername']"), 10);
                $this->savePageToLogs($selenium);
            }

            if ($key) {
                $this->DebugInfo = 'reCAPTCHA checkbox';
                $captcha = $this->parseReCaptcha($key->getAttribute('data-sitekey'));

                if ($captcha === false) {
                    $this->logger->error("failed to recognize captcha");

                    return false;
                }
                $selenium->driver->executeScript('window.handleCaptcha(' . json_encode($captcha) . ')');
            }// if ($key)

            $form = '//form[@id = "formLogin"]';
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@id = 'txtVIPorterUsername']"), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@id = 'txtVIPorterPassword']"), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath("{$form}//button[contains(@id, 'SubmitLogin')]"), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("something went wrong");

                return false;
            }
            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);

            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $selenium->driver->executeScript("try { document.getElementById('txt_RememberMe').click(); } catch (e) {}");

            if ($buttonOK = $selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'warning-modal']//button[@aria-hidden = 'false' and contains(text(), 'OK')]"), 3)) {
                $this->savePageToLogs($selenium);
                $buttonOK->click();
                sleep(1);
            }
            // save page to logs
            $this->savePageToLogs($selenium);

//            $button->click();
            $selenium->driver->executeScript("document.getElementsByName('LoginButton')[0].click()");

            $key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3);
            // save page to logs
            $this->savePageToLogs($selenium);

            if ($key) {
                $this->DebugInfo = 'reCAPTCHA checkbox';
                $captcha = $this->parseReCaptcha($key->getAttribute('data-sitekey'));

                if ($captcha === false) {
                    $this->logger->error("failed to recognize captcha");

                    return false;
                }
                $selenium->driver->executeScript('window.handleCaptcha(' . json_encode($captcha) . ')');
            }// if ($key)

            if ($this->clickCloudFlareCheckboxByMouse($selenium)) {
                $selenium->waitForElement(WebDriverBy::xpath("//div[@id and @style=\"display: grid;\"] | //div[@id=\"ulp-auth0-v2-captcha\"] | //div[@id=\"cf-turnstile\"] | //input[@id = 'txtVIPorterUsername'] | //a[contains(@href, 'Sign-Out') or contains(text(), 'Log Out')]"), 10);
                $this->savePageToLogs($selenium);
            }

            $success = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Sign-Out') or contains(text(), 'Log Out')]"), 10, false);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3))) {
                $this->DebugInfo = 'reCAPTCHA checkbox';
                $captcha = $this->parseReCaptcha($key->getAttribute('data-sitekey'));

                if ($captcha === false) {
                    $this->logger->error("failed to recognize captcha");

                    return false;
                }
                $selenium->driver->executeScript('window.handleCaptcha(' . json_encode($captcha) . ')');
                $success = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Sign-Out') or contains(text(), 'Log Out')]"), 10, false);
            }// if ($key)

            if (!$success) {
                $success = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Sign-Out') or contains(text(), 'Log Out')]"), 5, false);
            }

            if ($success) {
                $logout = true;
                $currentUrl = $this->http->currentUrl();
                $this->logger->debug("[Current URL]: {$currentUrl}");

                $this->savePageToLogs($selenium);
                // refs #20636
                $name = $this->http->FindSingleNode('//div[contains(@class, "c-viporter-dropdown-greeter")]', null, true, "/Hello, ([^!]+)!/");
                $this->logger->debug("[Name after auth]: {$name}");

                if (empty($name)) {
                    $this->logger->error("something went wrong, name not found");

                    return false;
                }

                if ($currentUrl != self::REWARDS_PAGE_URL) {
                    $selenium->http->GetURL(self::REWARDS_PAGE_URL);
                    $this->savePageToLogs($selenium);

                    if ($this->clickCloudFlareCheckboxByMouse($selenium)) {
                        $selenium->waitForElement(WebDriverBy::xpath("//div[@id and @style=\"display: grid;\"] | //div[@id=\"ulp-auth0-v2-captcha\"] | //div[@id=\"cf-turnstile\"] | //input[@id = 'txtVIPorterUsername'] | //a[contains(@href, 'Sign-Out') or contains(text(), 'Log Out')] | //div[contains(@class, \"c-viporter-dropdown-greeter\")]"), 10);
                        $this->savePageToLogs($selenium);
                    }

                    $nameRewardsPage = $this->http->FindSingleNode('//div[contains(@class, "c-viporter-dropdown-greeter")]', null, true, "/Hello, ([^!]+)!/");
                    $this->logger->debug("[Name after redirect]: {$nameRewardsPage}");

                    if ($name != $nameRewardsPage) {
                        $this->logger->error("something went wrong, names do not mismatch");

                        throw new CheckRetryNeededException(3, 0);
                    }
                }
                $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "getNumberWithCommas")]'), 10);

                $selenium->http->GetURL("https://www.flyporter.com/en-us/viporter/account");
                $this->savePageToLogs($selenium);
                $number = str_replace(' ', '', $this->http->FindSingleNode("//input[@name = 'viporterNum']/@value"));
                $this->logger->debug("[Number]: {$number}");
                $email = str_replace(' ', '', $this->http->FindSingleNode("//input[@name = 'viporterEmail']/@value"));
                $this->logger->debug("[Email]: {$email}");

                $this->logger->debug("[Email trunc]: " . strtolower(substr($email, 0, strpos($email, '@'))));
                $this->logger->debug("[Login trunc]: " . strtolower(substr($this->AccountFields['Login'], 0, strpos($this->AccountFields['Login'], '@'))));

                if (
                    (is_numeric($this->AccountFields['Login']) || filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false)
                    && $number !== $this->AccountFields['Login']
                    && strtolower($email) !== strtolower($this->AccountFields['Login'])
                    && strtolower($email) !== 'gordon@collectivenext.com'// login != email
                    && strtolower($email) !== 'clohr@me.com'// login != email
                    && strtolower($email) !== 'ac@ahouse12.com'// login != email
                    && strtolower($email) !== 'bestanley@bellaliant.net'// login != email
                    && strtolower($email) !== 'flyporter@daviesbros.ca'// login != email
                    && strtolower(substr($email, 0, strpos($email, '@'))) !== strtolower(substr($this->AccountFields['Login'], 0, strpos($this->AccountFields['Login'], '@')))// login != email
                ) {
                    throw new CheckRetryNeededException(3, 0); //todo
                }

                $selenium->http->GetURL(self::REWARDS_PAGE_URL);
                $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "c-viporter-dropdown-greeter")]'), 10);
                $this->savePageToLogs($selenium);
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            // provider bug fix
            if ($logout === false && $selenium->http->currentUrl() == 'https://www.flyporter.com/en-us/') {
                $retry = true;
            }
        } catch (NoSuchDriverException | WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }

        if (
            $retry
            && !in_array($this->AccountFields['Login'], $this->brokenAccounts)
            && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
        ) {
            $this->logger->debug("[attempt]: {$this->attempt}");

            throw new CheckRetryNeededException(3, 0);
        }

        return $logout;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Site maintenance
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'We are in the process of performing upgrades')]
                | //h2[contains(text(), 'We are in the process of performing upgrades, but should be up and running shortly.')]
                | //h1[contains(text(), 're making changes for you')]
            ")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Outages
        if ($message = $this->http->FindPreg("/We are experiencing system wide network outages affecting our website, Call Centre, reservation system and flight planning. Thank you for your patience while we work to provide updates./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            || $this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindSingleNode('//h2[contains(text(), "Service Unavailable")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "504 - Gateway Timeout")]')
            //# Server Error in '/' Application
            || $this->http->FindPreg('/(Server Error in \'\/\' Application\.)/ims')
            //# The page cannot be displayed because an internal server error has occurred
            || $this->http->FindPreg('/(The page cannot be displayed because an internal server error has occurred\.)/ims')
            // 503 Service Unavailable: Back-end server is at capacity
            || $this->http->Response['code'] == 503
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# The server encountered a temporary error
        if ($message = $this->http->FindPreg("/(The server encountered a temporary error\. Your request could not be completed\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // hard code
        if ($this->http->ParseForm('formLogin') && isset($this->http->Form['VIPorterNumberOrEmailOrUsername'])
            && $this->http->Form['VIPorterNumberOrEmailOrUsername'] == $this->AccountFields['Login']) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->disablePostForm === false && !$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode("//div[@class = 'errorMessage']")) {
            return $this->catchLoginErrors($message);
        }
        // Access is allowed
        if (strstr($this->http->currentUrl(), 'viporter/dashboard') && !strstr($this->http->currentUrl(), 'en-us')) {
            $this->http->GetURL("https://www.flyporter.com/en-us/viporter/dashboard");
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@class = 'errorMessage']", null, true, null, 0)) {
            return $this->catchLoginErrors($message);
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "An unknown error has occurred, please try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // recaptcha workaround
        if (
            $this->http->FindSingleNode("//h2[contains(text(), 'Please verify you are a human')]")
            || $this->http->FindPreg("/Validating JavaScript Engine/")
        ) {
            throw new CheckRetryNeededException(3, 0);
        }

        // hard code
        if (in_array($this->AccountFields['Login'], $this->brokenAccounts)) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $maintenance = $this->http->FindSingleNode("//p[contains(text(), 'Your VIPorter account information is temporarily unavailable due to routine system maintenance.')]");

        sleep(rand(0, 2));
        $this->http->RetryCount = 0;

        /*
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            if ($this->humanVerify()) {
            }
        }
        */
        // Balance - Points Balance
        $this->SetBalance($this->http->FindPreg('/const points\s*=\s*\'([^\']+)/ims'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[contains(@class, "c-viporter-dropdown-greeter")]', null, true, "/Hello, ([^!]+)!/")));
        // VIPorter Member Number
        $this->SetProperty("Number", $this->http->FindPreg('/const VIPorterNumber\s*=\s*\'([^\']+)/ims'));
        // Tier level
        $status = $this->http->FindPreg("/'VIPorterTierType':\s*'([^\']+)/");

        switch ($status) {
            case 'BAS':
                $this->SetProperty("Tier", "Member");

                break;

            case 'PSP':
                $this->SetProperty("Tier", "Passport");

                break;

            case 'PRI':
                $this->SetProperty("Tier", "Venture");

                break;

            case 'ASC':
                $this->SetProperty("Tier", "Ascent");

                break;

            case 'FIR':
                $this->SetProperty("Tier", "First");

                break;

            default:
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    $balance = $this->http->FindSingleNode("//div[contains(@class, 'loginInfo')][3]", null, true, "/:\s*([^<]+)/ims");
                    $this->logger->debug("Balance '{$balance}'");

                    if (empty($status) && $maintenance && $balance == 'Not Available') {
                        $this->SetWarning($maintenance);

                        return;
                    }// if (empty($status) && $maintenance && $balance == 'Not Available')
                }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

                if ($this->ErrorCode == ACCOUNT_CHECKED) {
                    $this->sendNotification("Unknown status: $status");
                }
        }// switch ($status)

        // Qualifying spend, year to date (YTD Qualifying spend)
        $this->SetProperty("QualifyingSpend", "$" . $this->http->FindSingleNode("//div[@class = 'dashboard-pts__header']/div[@class = 'dashboard-pts__balance']/p[contains(., 'dollar')]/text()[1]"));
//        // Additional spend to re-qualify for ... (Spend to re-qualify level)
//        $this->SetProperty("AdditionalSpend", $this->http->FindSingleNode("//div[contains(text(), 'Additional spend to re-qualify')]/following-sibling::div[1]"));

        // Expiration date  // refs #11232

        // new logic

        $lastDate = $this->http->FindPreg("/\{\"ServiceDate\":\"..Date\((\d+)\d{3}\)..\",\"ServiceDateFormatted\":\"[^\"]+\",\"PartnerCode\":null,\"RecordLocator\":[^,]+,\"Comments\":null,\"QualifyingPoints\":[\-\d]+,\"RedeemablePoints\":[\-\d]+,\"TransactionID\":\d+,\"TransactionType\":\d+,\"ArrivalStation\":\"/");
        $this->logger->debug("lastServiceDate: " . $lastDate);
        $lastServiceDate = date('Y-m-d', $lastDate);
        $lastTransactionID = $this->http->FindPreg("/\{\"ServiceDate\":\"..Date\(\d+\)..\",\"ServiceDateFormatted\":\"[^\"]+\",\"PartnerCode\":null,\"RecordLocator\":[^,]+,\"Comments\":null,\"QualifyingPoints\":[\-\d]+,\"RedeemablePoints\":[\-\d]+,\"TransactionID\":(\d+),\"TransactionType\":\d+,\"ArrivalStation\":\"/");
        $recordLocator = $this->http->FindPreg("/\{\"ServiceDate\":\"..Date\(\d+\)..\",\"ServiceDateFormatted\":\"[^\"]+\",\"PartnerCode\":null,\"RecordLocator\":([^,]+),\"Comments\":null,\"QualifyingPoints\":[\-\d]+,\"RedeemablePoints\":[\-\d]+,\"TransactionID\":\d+,\"TransactionType\":\d+,\"ArrivalStation\":\"/");
        $this->logger->debug("lastServiceDate: {$lastServiceDate} / RecordLocator: {$recordLocator}");

        if ($this->Balance > 0 && isset($lastDate, $lastTransactionID)) {
            if (is_null($recordLocator)) {
                $headers = [
                    "X-Requested-With" => "XMLHttpRequest",
                    "Content-Type"     => "application/json",
                    "Accept"           => "*/*",
                ];
                $this->http->PostURL("https://www.flyporter.com/VIPorter/Transactions", '{"lastServiceDate":"","lastTransactionID":"","total24MonthsCount":26}', $headers);
                $response = $this->http->JsonLog(null, true, true);
                $transactions = ArrayVal($response, 'data', []);

                foreach ($transactions as $transaction) {
                    $date = $this->http->FindPreg("/Date\((\d+)\d{3}\)/", false, $transaction['ServiceDate']);
                    $recordLocator = $transaction['RecordLocator'];
                    $this->logger->debug("Date: {$date} / RecordLocator: {$recordLocator}");

                    if (!is_null($recordLocator)) {
                        $this->SetProperty("LastActivity", date("M d, Y", $lastDate));
                        $this->SetExpirationDate(strtotime("+2 year", $lastDate));

                        break;
                    }// if (!is_null($recordLocator))
                }// foreach ($transactions as $transaction)
            }// if (is_null($recordLocator))
            elseif (isset($lastDate, $lastTransactionID)) {
                $this->SetProperty("LastActivity", date("M d, Y", $lastDate));
                $this->SetExpirationDate(strtotime("+2 year", $lastDate));
            }// elseif (isset($lastDate, $lastTransactionID))

            // https://redmine.awardwallet.com/issues/11232#note-23
            if (isset($this->Properties["LastActivity"], $this->Properties["AccountExpirationDate"])
                && $this->Properties["AccountExpirationDate"] < strtotime("31 Dec 2023")
            ) {
                $this->logger->notice("Set ExpirationDate as 31 Dec 2023");
                $this->SetExpirationDate(strtotime("31 Dec 2023"));
            }
        }// if ($this->Balance > 0 && isset($lastDate, $lastTransactionID))
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.flyporter.com/';

        return $arg;
    }

    protected function humanVerify()
    {
        $this->logger->notice(__METHOD__);

        if (
            $url = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"10; url=(\/distil_r_captcha.html([^\"]+))/")
        ) {
            $this->http->GetURL("https://www.flyporter.com" . $url);
        } elseif (
            $url = $this->http->FindSingleNode('//iframe[@id = "main-iframe"]/@src')
        ) {
            $currentUrl = $this->http->currentUrl();
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);

            $postUrl = $this->http->FindPreg("/\"POST\", \"(\/_Incapsula_Resource\?SWCGHOEL[^\"]+)/");
            $dataSource = $this->http->FindPreg("/GET\", \"(\/_Incapsula_Resource\?SWCNGEEC=[^\"]+)/");

            if ($dataSource && $postUrl) {
                $this->http->NormalizeURL($postUrl);
                $this->http->NormalizeURL($dataSource);
                $this->http->GetURL($dataSource);

                $responseStr = $this->http->JsonLog();

                if (!isset($responseStr->challenge) || !isset($responseStr->gt)) {
                    return false;
                }
                $currentUrl = "https://www.flyporter.com/viporter/account-summary?culture=en-US";
                $captcha = $this->parseGeettestRuCaptcha($responseStr->gt, $responseStr->challenge, $currentUrl);
                $data = [
                    'geetest_challenge' => $captcha->geetest_challenge,
                    'geetest_validate'  => $captcha->geetest_validate,
                    'geetest_seccode'   => $captcha->geetest_seccode,
                ];
                $this->http->RetryCount = 0;
                $headers = [
                    'Accept'       => '*/*',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ];
                $this->http->PostURL($postUrl, $data, $headers);
                $this->http->RetryCount = 2;

                $this->http->GetURL($currentUrl);

                return true;
            }
        }

        if (
            $this->attempt > 1
            || $this->http->FindSingleNode("
                //h2[contains(text(), 'Please verify you are a human')]
                | //h1[contains(text(), 'Pardon Our Interruption...')]
                ")
        ) {
            $this->parseGeetestCaptcha();
//            $this->selenium();
        } else {
            return false;
        }

        return true;
    }

    protected function parseGeettestRuCaptcha($gt, $challenge, $pageurl)
    {
        $this->logger->notice(__METHOD__);
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $pageurl,
            "proxy"      => $this->http->GetProxy(),
            'api_server' => 'api.geetest.com',
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
        $request = $this->http->JsonLog($captcha);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
            $request = $this->http->JsonLog($captcha);
        }

        if (empty($request)) {
            $this->logger->error("geetestFailed = true");

            return false;
        }

        return $request;
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        // key from https://captcha.perimeterx.net/PXnrdalolX/captcha.js?a=c&u=c7349500-2a90-11e9-a4a6-93984e516e46&v=&m=0
//        $key = '6Lcj-R8TAAAAABs3FrRPuQhLMbp5QrHsHufzLf7b';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key/* || !$this->http->FindSingleNode("//script[contains(@src, 'https://captcha.perimeterx.net/PXnrdalolX/captcha')]/@src")*/) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'Sign-Out') or contains(@href, 'sign-out') or contains(text(), 'Log Out')]/@href")) {
            return true;
        }

        return false;
    }

    private function parseGeetestCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $gt = $this->http->FindPreg("/gt:\s*'(.+?)'/");
        $apiServer = $this->http->FindPreg("/api_server:\s*'(.+?)'/");
        $ticket = $this->http->FindSingleNode('//input[@name = "dCF_ticket"]/@value');

        if (!$gt || !$apiServer || !$ticket) {
            $this->logger->notice('Not a geetest captcha');

            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        /** @var HTTPBrowser $http2 */
        $http2 = clone $this->http;
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
        $request = $this->http->JsonLog($captcha, true, true);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
            $request = $this->http->JsonLog($captcha, true, true);
        }

        if (empty($request)) {
            $this->logger->error("geetest failed = true");

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->http->NormalizeURL($verifyUrl);
        $payload = [
            'ticket'            => $ticket,
            'geetest_challenge' => $request['geetest_challenge'],
            'geetest_validate'  => $request['geetest_validate'],
            'geetest_seccode'   => $request['geetest_seccode'],
        ];
        $this->http->PostURL($verifyUrl, $payload);

        return true;
    }

    private function catchLoginErrors($message)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->error("[Error]: {$message}");

        if (strstr($message, 'Your account has been locked due to too many failed password attempts.')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if (
            strstr($message, 'Incorrect login information')
            || strstr($message, 'Invalid email address. Please try again.')
            || strstr($message, 'Oops, something doesn’t seem right! Please verify your login credential')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (strstr($message, 'Something doesn’t seem right. We were expecting a different answer. Please try again.')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->DebugInfo = $message;

        return false;
    }
}
