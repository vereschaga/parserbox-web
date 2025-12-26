<?php

// refs #1692, hallmark

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerHallmark extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public $regionOptions = [
        ""      => "Select your region",
        "USA"   => "United States",
        "Dutch" => "Dutch",
    ];
    private $notRegistered = false;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->setHttp2(true);

        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
    }

    public function IsLoggedIn()
    {
        switch ($this->AccountFields['Login2']) {
            case 'Dutch':
                break;

            default:
                $accessToken = $this->http->getCookieByName("accessToken", ".hallmark.com");
                $accountId = $this->http->getCookieByName("accountId", ".hallmark.com");

                if (isset($accessToken, $accountId)) {
                    $this->http->RetryCount = 0;
                    $this->http->GetURL("https://my.hallmark.com/api/accounts/getProfile", [], 20);
                    $this->http->RetryCount = 2;
                    $response = $this->http->JsonLog();

                    if (isset($response->body->Email, $response->body->LastName)) {
                        return true;
                    }
                }

                break;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return call_user_func([$this, "LoadLoginFormOf" . $this->AccountFields['Login2']]);
    }

    public function LoadLoginFormOfUSA()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("That email address isn't formatted correctly. Make sure it contains an “@” and a domain like “.com” or “org.", ACCOUNT_INVALID_PASSWORD);
        }

        // TODO: Incapsula
        return $this->selenium();
        $this->http->GetURL('https://www.hallmark.com/login/');
//
//        // maintenance
//        if (($message = $this->http->FindSingleNode("//img[contains(@alt, 'Our Site is feeling a bit down.')]/@alt"))
//            && $this->http->currentUrl() == 'http://account.hallmark.com/maintenance.html')
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

        $csrf = $this->http->FindPreg("/\"csrf_token\",\s*\"value\":\s*\"([^\"]+)/");

//        if (!$this->http->ParseForm("login-form") || !$csrf) {
        if (!$csrf) {
            return $this->checkErrors();
        }

        $captcha = $this->parseCaptcha('6Le0lXEpAAAAAO3tTOqyDo4kSKOQFNuDyfXB1r-x');

        if ($captcha === false) {
            return false;
        }

        $this->http->FormURL = 'https://www.hallmark.com/on/demandware.store/Sites-hmk-onesite-Site/default/Account-Login?rurl=1&ajax=true';
        $this->http->Form = [];
        $this->http->SetInputValue("dwfrm_login_email", $this->AccountFields['Login']);
        $this->http->SetInputValue("dwfrm_login_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("dwfrm_login_rememberMe", "true");
        $this->http->SetInputValue("dwfrm_login_recaptcha_googleRecaptcha", $captcha);
        $this->http->SetInputValue("csrf_token", $csrf);

        return true;
    }

    public function LoadLoginFormOfDutch()
    {
        $this->logger->notice(__METHOD__);
        $this->http->MultiValuedForms = true;
        $this->http->setDefaultHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
        $this->http->GetURL('https://www.hallmark.nl/inloggen/?returnUrl=/');

        if (!$this->http->ParseForm(null, "//form[contains(@action, '/Security/Login')]")) {
            return false;
        }
        $this->http->SetInputValue("EmailAddress", $this->AccountFields['Login']);
        $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("X-Requested-With", "XMLHttpRequest");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        if ($this->AccountFields['Login2'] != "Dutch") {
            $arg['NoCookieURL'] = true;
            $arg['PreloadAsImages'] = true;
            $arg['SuccessURL'] = 'https://my.hallmark.com/#/cr/rewards';
        } else {
            $arg['SuccessURL'] = 'http://www.hallmark.nl/';
        }

        return $arg;
    }

    public function Login()
    {
        return call_user_func([$this, "LoginOf" . $this->AccountFields['Login2']]);
    }

    public function LoginOfDutch()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->PostForm()) {
            return false;
        }
        $response = $this->http->JsonLog();

        if (isset($response->IsResponseSuccesfull, $response->CompleteErrorMessages[0])) {
            throw new CheckException($response->CompleteErrorMessages[0], ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($response->IsResponseSuccesfull) && $response->IsResponseSuccesfull == 'true') {
            return true;
        }

        return false;
    }

    public function LoginOfUSA()
    {
        $this->logger->notice(__METHOD__);
        /*
        $this->http->RetryCount = 0;

        $headers = [
            "Accept"                => "application/json",
            "User-Agent"            => \HttpBrowser::PROXY_USER_AGENT,
            "Referer"               => "https://www.hallmark.com/login/no-referrer",
            "x-sf-cc-siteid"        => "hmk-onesite",
            "x-sf-cc-requestlocale" => "default",
            "x-requested-with"      => "XMLHttpRequest",
            "Content-Type"          => "application/x-www-form-urlencoded",
        ];

        $this->http->PostForm($headers);

        $this->http->RetryCount = 2;
        */
        $this->http->JsonLog();

        if ($this->http->getCookieByName("name") || $this->http->FindNodes('//button[@data-tau="logout_submit"]')) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@data-tau=\"global_alerts_item\"]")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, "The email or password you entered doesn’t match our records.")
                || strstr($message, "Sorry, your email or password doesn't match our records")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Unexpected error occurred. Please try later') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

//            if (strstr($message, "We're sorry. It looks like something went wrong.")) {
//                $this->ErrorReason = self::ERROR_REASON_BLOCK;
//                $this->DebugInfo = "captcha issue";
//
//                return false;
//            }

            $this->DebugInfo = $message;

            return false;
        }

        // We're sorry. Either your email address or password don’t match our records. Please try again.
        if ($this->http->FindPreg("/Message\":\"Invalid LoginID/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("We're sorry. Either your email address or password don’t match our records. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }
        // We're sorry. It looks like something went wrong. Please try again or call 1-800-Hallmark if the problem continues.
        if (
            $this->http->FindPreg("/Message\":\"(?:Password should be a minimum of 1 character and cannot exceed 15 characters|Account Pending Registration|Old Password Used|General Server Error)\"/")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("We're sorry. It looks like something went wrong. Please try again or call 1-800-Hallmark if the problem continues.", ACCOUNT_PROVIDER_ERROR);
        }

        // wrong captcha answer
        if ($this->http->FindPreg("/Message\":\"Failed captcha verification/")) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
        }

        if ($this->http->FindPreg("/(?:An error occured, please try again or contact the administrator|\"Message\":\"System.Net.WebException: The remote server returned an error: \(500\) Internal Server Error\.)/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(3, 1);
        }

        return $this->checkErrors();
    }

    public function notMemberError()
    {
        $this->logger->notice(__METHOD__);
        // If you have recently enrolled, it may take up to 10 days for your account information to become available online
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are sorry, but we are unable to access your account information')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // There’s never been a better time to join!
        if ($message = $this->http->FindPreg("/parent\.window\.location\.replace\(\'http:\/\/www\.hallmark\.com\/online\/crown\-rewards\/\'\)\;/")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Hallmark.com will be back up soon
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Hallmark.com will be back up soon') or contains(text(), 'We’re doing a little')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // A technical problem
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'A technical problem')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is feeling a bit down.
        if (
            $this->http->FindSingleNode("//img[contains(@src, '/hallmark-resources/images/error-500.png')]/@src")
            || $this->http->currentUrl() == 'http://www.hallmark.com/maintenance.html'
            || $this->http->FindSingleNode("//img[@src = '/hallmark-resources/error/images/explore-404-message.png']/@src")
        ) {
            throw new CheckException('Our website is feeling a bit down. Please try to access it later.', ACCOUNT_PROVIDER_ERROR); /*checked*/
        }
        // Site is unavailable
        if ($message = $this->http->FindSingleNode("//div[contains(@class,'pageMaintenance')]/div/div/p[@class= 'xlarge'] | //p[contains(text(), 're making sure everything is ready for our Winter Clearance Sale')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if (
            $this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // iisnode encountered an error when processing the request.
        if ($this->http->FindPreg('/iisnode encountered an error when processing the request./')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        return call_user_func([$this, "parseOf" . $this->AccountFields['Login2']]);
    }

    public function parseOfDutch()
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://www.hallmark.nl/mijn-account/Discounts/LoyaltyCards");
        $this->http->FilterHTML = true;
        // Balance - Mijn Hallmark-spaarkaart
        $this->SetBalance(count($this->http->FindNodes("//div[@class = 'stamp filled']")));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'content']/h2")));

        $this->http->GetURL("http://www.hallmark.nl/mijn-account/Profile.aspx");
        $name = Html::cleanXMLValue($this->http->FindSingleNode('//input[@id = "FirstName"]/@value')
            . ' ' . $this->http->FindSingleNode('//input[@name = "LastName"]/@value'));

        if (!empty($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public function parseOfUSA()
    {
        $this->logger->notice(__METHOD__);
        $accessToken = $this->http->getCookieByName("accessToken", ".hallmark.com");
        $accountId = $this->http->getCookieByName("accountId", ".hallmark.com");

        if (isset($accessToken, $accountId)) {
            if (!$this->http->FindPreg('#/api/accounts/getProfile#', false, $this->http->currentUrl())) {
                $this->http->GetURL("https://my.hallmark.com/api/accounts/getProfile");
            }
            $response = $this->http->JsonLog();
            // Name
            if (isset($response->body->FirstName, $response->body->LastName)) {
                $this->SetProperty("Name", beautifulName($response->body->FirstName . " " . $response->body->LastName));
            }

            $CRNumber = null;

            if (isset($response->body->CRNumber) && $response->body->CRNumber == 0) {
                $CRNumber = $response->body->CRNumber;
            }

//            $this->http->GetURL("https://my.hallmark.com/api/crmemberships/createCrossPortalLoginUrl?consumerId={$accountId}&consumerToken={$accessToken}");
            $this->http->GetURL("https://my.hallmark.com/api/crmemberships/getCrownRewardsMembership?consumerId={$accountId}&consumerToken={$accessToken}");
            $response = $this->http->JsonLog();

            $this->SetBalance($response->PointsAvailable ?? null);
            // Year to Date Points
            $this->SetProperty('YTDPoints', $response->YearToDatePoints ?? null);
            // Status
            $type = $response->Tier ?? null;

            if (isset($type) && preg_match('/^(.*) MEMBER/ims', $type, $m)) {
                $type = $m[1];
            }
            $this->SetProperty("Type", $type);
            // To next Level
            $this->SetProperty("ToNextLevel", $response->PointsToMaintainTierLevel ?? null);
            // Member Number
            $this->SetProperty("Number", $response->CRNumber ?? null);

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                // not a member
                if (
                    !empty($this->Properties['Name'])
                    && (
                       (empty($this->http->Response['body']) && $this->http->Response['code'] == 200 && $CRNumber == 0)
                       || ($this->http->Response['code'] == 404 && $this->http->FindPreg("/^Not Found$/"))
                    )
                ) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

            if ($this->notRegistered) {
                throw new CheckException("Hallmark (Crown Rewards) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->Response['code'] == 500 && $this->http->FindPreg("/^Internal server error\.$/")) {
                throw new CheckException("We're sorry. We're having trouble retrieving your rewards. Please wait a few minutes and then reload the page to try again.", ACCOUNT_PROVIDER_ERROR);
            }

            $this->logger->debug("[Error]: '{$this->http->Error}'");
            if (trim($this->http->Error) == "Network error 1 - Received HTTP/0.9 when not allowed") {
                throw new CheckException("We're sorry. We're having trouble retrieving your rewards. Please wait a few minutes and then reload the page to try again.", ACCOUNT_PROVIDER_ERROR);
            }
        }// if (isset($accessToken, $accountId))
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'USA';
        }

        return $region;
    }

    protected function parseCaptchaUSA($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV2TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "isInvisible"  => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        //$key = $this->http->FindSingleNode("(//form[@name='member-login-form']//div[@id='login-desk-captcha']/@data-sitekey)[1]");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => 'https://account.hallmark.com/#/signin?URL=https:%2F%2Fmy.hallmark.com%2F%23%2Fcr%2Frewards',
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            "pageAction"   => "LOGIN",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => 'https://account.hallmark.com/#/signin?URL=https:%2F%2Fmy.hallmark.com%2F%23%2Fcr%2Frewards',
            "proxy"     => $this->http->GetProxy(),
            "version"   => "enterprise",
            "invisible" => 1,
            "action"    => "LOGIN",
            "min_score" => 0.7,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
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

            $selenium->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
            $selenium->useFirefoxPlaywright();
//            $selenium->useFirefox();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.hallmark.com/login/");
            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "dwfrm_login_email"]'), 10);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "dwfrm_login_password"]'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign in")]'), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$pass || !$btn) {
                return $this->checkErrors();
            }

            $selenium->driver->executeScript('document.querySelector(\'input[id="dwfrm_login_rememberMe_dwfrm_login_rememberMe"]\').checked = true;');
//            $login->sendKeys($this->AccountFields['Login']);
            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($login, $this->AccountFields['Login'], 5);
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);

            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath("//h3[contains(@class, 'user_greeting-title')] | //div[@data-tau=\"global_alerts_item\"] | //p[contains(text(), 're making sure everything is ready for our Winter Clearance Sale')]"), 10);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }
}
