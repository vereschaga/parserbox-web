<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerWayfair extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public const REWARDS_PAGE_URL = 'https://www.wayfair.com/session/secure/account/rewards_balance.php';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
        $this->http->SetProxy($this->proxyReCaptcha());
        */
        $this->http->setHttp2(true);
        $this->setProxyGoProxies();
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
//        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->GetURL("https://www.wayfair.com/v/account/welcome/show", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Invalid email address. Please try again.', ACCOUNT_INVALID_PASSWORD);
        }

//        $this->http->removeCookies();

        if (strstr($this->http->currentUrl(), 'https://www.wayfair.com/v/captcha/show?')) {
            $this->http->removeCookies();
            $this->seleniumAuth();
        }

        $this->http->GetURL('https://www.wayfair.com/session/secure/account/login.php');

        // distil script
        if ($distil = $this->http->FindSingleNode("//meta[contains(@src, '/distil_r_blocked.html')]/@src")) {
            $this->logger->debug("distil script, try to recognize captcha.");
            $this->http->NormalizeURL($distil);
            $this->http->GetURL($distil);
            // reCaptcha
            $this->reCaptcha();
        }// if ($distil = $this->http->FindSingleNode("//meta[contains(@src, '/distil_r_blocked.html')]/@src"))
        // reCaptcha
        $this->reCaptcha();

        if ($this->http->ParseForm(null, "//form[contains(@action, '/session/secure/account/login.php') and @class='accreturningcustomers']")) {
            $this->logger->notice("Try old login form");
            $this->http->SetInputValue('login_email', $this->AccountFields['Login']);
            $this->http->SetInputValue('login_password', $this->AccountFields['Pass']);
            $this->http->SetInputValue('login_submit', 'Log Into My Account');
        } elseif ($this->http->ParseForm(null, "//form[contains(@action, '/v/customer/login')]")) {
            $this->logger->notice("Try new login form, v.2");
            $this->http->SetInputValue('email', $this->AccountFields['Login']);
            $this->http->PostForm();

            if (!$this->http->ParseForm(null, "//form[contains(@action, '/v/customer/login')]")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        } elseif (
            $this->http->ParseForm(null, "//form[contains(@action, '/v/account/authentication/login') or contains(@class, 'AuthLoginForm')]", false)
            || $this->http->FindSingleNode("//form[contains(@action, '/v/account/authentication/login') or contains(@class, 'AuthLoginForm')]")
            || ($this->State['csrfToken'] = $this->http->FindPreg('/"csrfToken":"(.+?)",/'))
            || ($this->State['csrfToken'] = $this->http->FindPreg('/"_csrf_token","CSRF_TOKEN":"(.+?)",/'))
        ) {
            $this->logger->notice("Try new login form, v.4");
            $this->State['csrfCsn'] = $this->http->getCookieByName('CSN_CSRF', '.wayfair.com');
            $this->State['txid'] = $this->http->Response['headers']['txid'] ?? null;

            $data = [
                'email'                => $this->AccountFields['Login'],
                'password'             => $this->AccountFields['Pass'],
                'step'                 => 'password',
                '_csrf_token'          => $this->State['csrfToken'],
                'isFromLoginComponent' => 'true',
                'recaptchaResponse'    => null,
            ];
            $headers = [
                'X-Parent-TXID'    => 'I/WEwl17Xd+nuQpOV6wtAg==',
                'Accept'           => 'application/json',
                'Content-Type'     => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'Origin'           => 'https://www.wayfair.com',
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.wayfair.com/a/account/authentication/login', json_encode($data), $headers);
            $this->http->RetryCount = 2;

            if ($this->http->Response['body'] == '{"captcha":1}') {
                $this->logger->notice("reCaptcha");
                $this->DebugInfo = 'reCaptcha';

                $this->seleniumAuth(true);

                return false;

                $this->http->RetryCount = 0;
                $this->http->PostURL('https://www.wayfair.com/a/account/authentication/login', json_encode($data), $headers);
                $this->http->RetryCount = 2;

                //throw new CheckRetryNeededException(2, 10);
//                $captcha = $this->parseReCaptcha();
//                if ($captcha === false) {
//                    return false;
//                }
//
//                $data['recaptchaResponse'] = $captcha;
//                $this->http->RetryCount = 0;
//                $this->http->PostURL('https://www.wayfair.com/a/account/authentication/login', json_encode($data), $headers);
//                $this->http->RetryCount = 2;
            }// if ($this->http->Response['body'] == '{"captcha":1}')
        } elseif ($this->http->ParseForm(null, "//form[contains(@action, '/v/account/authentication/login')]")) {
            $this->logger->notice("Try new login form, v.3");
            $this->http->SetInputValue('email', $this->AccountFields['Login']);
            $this->http->PostForm();
            // reCaptcha
            $this->reCaptcha();

            // first login from one more time
            if ($this->http->FindSingleNode("//p[contains(text(), 'Enter your email address to sign in or to create an account')]")
                && $this->http->ParseForm(null, "//form[contains(@action, '/v/account/authentication/login')]")) {
                $this->logger->notice("Try new login form, v.3");
                $this->http->SetInputValue('email', $this->AccountFields['Login']);
                $this->http->PostForm();
            }

            // We're sorry, but your account has been temporarily locked out
            if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry, but your account has been temporarily locked out")]', null, true, "/We're sorry, but your account has been temporarily locked out/")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            // Add a password to finish setting up your account
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Add a password to finish setting up your account')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (!$this->http->ParseForm(null, "//form[contains(@action, '/v/account/authentication/login')]")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('email', $this->AccountFields['Login']);
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        }
        // new form?
        else {
            $this->logger->notice("Try new login form, v.1.5");

            if (!$this->http->ParseForm("account_login")) {
                return $this->checkErrors();
            }

            $this->http->SetInputValue('email', $this->AccountFields['Login']);
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
            $this->http->SetInputValue('login_type', 'login');
            $this->http->SetInputValue('login_submit', 'Log In Using Our Secure Server');
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // this mean that servers have benn blocked
        if ($this->http->Response['code'] == 416) {
            $this->http->GetURL("https://www.wayfair.com/");

            if ($message = $this->http->FindSingleNode('//span[contains(text(), "We\'re sorry, so many friends are visiting we\'re having brief delays")]')) {
                $this->DebugInfo = self::ERROR_REASON_BLOCK;
            }
//                throw new CheckException("E'hem. Excuse us. We're sorry, so many friends are visiting we're having brief delays. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }// if ($this->http->Response['code'] == 416)

        // To proceed, please verify that you are not a robot.
        if ($this->http->FindSingleNode("//h1[contains(text(),'To proceed, please verify that you are not a robot.')]")) {
            throw new CheckRetryNeededException(1, 10/*, self::CAPTCHA_ERROR_MSG*/);
        }

        return false;
    }

    public function Login()
    {
//        if (!$this->http->PostForm())
//            return $this->checkErrors();
        $response = $this->http->JsonLog();
        // Password is incorrect. Please try again.
        if ($this->http->FindPreg('/"wrong_password":"(The password you entered isn\'t correct.)"/')) {
            throw new CheckException('Password is incorrect. Please try again.', ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/"account_temp_locked":"(We\'re sorry, but your account has been temporarily locked out, see <)/')) {
            throw new CheckException("We're sorry, but your account has been temporarily locked out. To access your account, please reset your password.", ACCOUNT_LOCKOUT);
        }

        // reCaptcha
        $this->reCaptcha();

        $response = $this->http->JsonLog();
        // redirect
        if (isset($response->redirect, $response->destinationURL) && $response->redirect == true) {
            $this->http->GetURL($response->destinationURL);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(@class, 'alerttext')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($response->webAuthnStatus, $response->showEmailOtp) && $response->webAuthnStatus === false && $response->showEmailOtp === true) {
            return $this->parseQuestion($response);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $sendPasswordLinkURL = $response->sendPasswordLinkURL ?? null;
        // Please Create a Password to Continue
        if ($this->http->FindPreg("/\"fromIdeaboards\":false,\"isEmailSent\":true,/")
            || stripos($sendPasswordLinkURL, "https://www.wayfair.com/v/account/authentication/send_password_link?") !== false
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->currentUrl() == 'https://www.wayfair.com/v/account/welcome/show') {
            $this->http->GetURL('https://www.wayfair.com/v/account/rewards/balance');

            if ($this->loginSuccessful()) {
                return true;
            }
        }

        // user don't have an account
        if (isset($response->step) && $response->step == 'create') {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(@class, "InputValidationText")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Password is incorrect. Please try again.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $data = json_encode([
            'email'       => $this->AccountFields['Login'],
            'password'    => $answer,
            '_csrf_token' => $this->State['csrfToken'],
        ]);
        $headers = [
            "Accept"                => "application/json",
            "Content-Type"          => "application/json",
            "x-auth-caller-context" => "auth_main_page_context",
            "x-csn-csrf-token"      => $this->State['csrfCsn'],
            "x-parent-txid"         => $this->State['txid'],
            "X-Requested-With"      => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.wayfair.com/auth/v2/email/grant", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        $error = $response->message ?? null;

        if ($error == "Code entered doesn't match the one we sent. Check your code and try again.") {
            $this->AskQuestion($this->Question, $error, "Question");

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.wayfair.com/account/balance');

        $headers = [
            "Accept"                       => "application/json",
            "Referer"                      => "https://www.wayfair.com/v/account/welcome/show",
            "X-Application"                => "AccountWelcomePage",
            "Content-Type"                 => "application/json",
            "X-Parent-TXID"                => "I/WEwmcXiPIWXmL76XlpAg==",
            "apollographql-client-name"    => "my_account_application",
            "apollographql-client-version" => "monolith",
            "Use-Path"                     => "true",
            "Origin"                       => "https://www.wayfair.com",
            "Alt-Used"                     => "www.wayfair.com",
        ];
        $this->http->PostURL('https://www.wayfair.com/graphql?queryPath=my_account_welcome_page_container~0', '{"query":"my_account_welcome_page_container~0","variables":{"orderItems":1,"features":["review_banner","show_waychat_messaging_center","refer_a_friend","customer_photos","review_sweepstakes","show_review_progress_widget","enable_tried_and_true_membership_card","should_show_my_way_offer","show_myway_account_section_and_page","enable_physical_retail_in_store_code"],"unleashFeatures":["enable_wayfair_rewards_multiplier_placements"],"maxProducts":2}}', $headers);
        $response = $this->http->JsonLog(null, 3, false, "currentRewardsDollars");

        /*
        $headers = [
            "Accept"                       => "*
        /*",
            "Referer"                      => "https://www.wayfair.com/account/balance",
            "wf-pageview-id"               => "NjIzMWVhNWYtY2FmNC00Mw==",// todo
            "wf-locale"                    => "en-US",
            "wf-store-id"                  => "49",
            "wf-b2b-experience"            => "false",
            "wf-b2b-aisle"                 => "null",
            "wf-anchor-pageview-id"        => "NjIzMWVhNWYtY2FmNC00Mw==",// todo
            "content-type"                 => "application/json",
            "x-wf-way"                     => "true",
            "x-parent-txid"                => "I/WEwmcXdq+hr2bMwQblAg==",
            "x-wayfair-locale"             => "en-US",
            "x-wayfair-host-override"      => "www.wayfair.com",
            "apollographql-client-name"    => "@wayfair/sf-ui-core-funnel",
            "apollographql-client-version" => "local",
            "Origin"                       => "https://www.wayfair.com",
            "Alt-Used"                     => "www.wayfair.com",
        ];
        $this->http->PostURL('https://www.wayfair.com/federation/graphql', '{"operationName":"AccountBalanceExperience","variables":{},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"b157c51d3363445c4edbab586d8c84c3e54f9f15f291377d4e20696065fb4e57"}}}', $headers);
        $response = $this->http->JsonLog();
        */

        $balance =
            $this->http->FindSingleNode("//strong[@id = 'earned_rewards_balance_header']")
//            ?? $response->data->wayfairRewardsLoyaltyBalance->
            ?? $response->data->me->customer->wayfairRewardsDollars->currentBalance
            ?? ($this->http->FindPreg("/\"wayfairRewardsDollars\":null,/") ? 0 : null)
            ?? null
        ;
        $this->logger->debug("[Balance]: {$balance}");
        // if offer in header was found
        // My Rewards Dollars - new design?
        if (!isset($balance)) {
            $balance =
                $this->http->FindSingleNode("//div[header[normalize-space(.)='My Rewards Dollars']]/following-sibling::div/p[@class='AccountBalancePage-balance-amount']")
                ?? $this->http->FindSingleNode("//div[header[normalize-space(.)='My Reward Dollars']]/following-sibling::div/p[@class='AccountBalancePage-balance-amount']")
                ?? $this->http->FindSingleNode('//div[contains(@class, "rewards_balance xltitle")]')
            ;
            $expDate =
                $this->http->FindSingleNode("//div[header[normalize-space(.)='My Rewards Dollars']]/following-sibling::div/p[@class='AccountBalancePage-balance-expiration']", null, false, '/Expires\s+(.+)/')
                ?? $this->http->FindSingleNode("//div[header[normalize-space(.)='My Reward Dollars']]/following-sibling::div/p[@class='AccountBalancePage-balance-expiration']", null, false, '/Expires\s+(.+)/')
                ?? $this->http->FindSingleNode('//span[contains(text(), "Rewards Expire On:")]/following-sibling::span[1]')
            ;
        } elseif (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(@class, 'ProductReviewBanner-balance')]/text()[1]");

            if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
                $this->http->GetURL(self::REWARDS_PAGE_URL);
            }
            // Rewards Expire On
            $expDate = $this->http->FindSingleNode("//ul[li[contains(text(), 'Rewards balance')]]/following-sibling::p[contains(text(), 'Expires')]", null, true, "/\:\s*([^<]+)/");
            // Wayfair Rewards Balance
            if (!isset($balance)) {
                $balance = $this->http->FindSingleNode("//ul[li[contains(text(), 'Rewards balance')]]/following-sibling::p[contains(@class, 'PlccAccount-rewards-amount')]");
            }
//                $balance = $this->http->FindSingleNode("//div[contains(text(), 'Wayfair Rewards Balance')]/following-sibling::div[1]");
            // My Rewards Dollars - new design?
            if (!isset($balance)) {
                $balance =
                    $this->http->FindSingleNode("//div[header[normalize-space(.)='My Rewards Dollars']]/following-sibling::div/p[@class='AccountBalancePage-balance-amount']")
                    ?? $this->http->FindSingleNode('//div[contains(@class, "rewards_balance xltitle")]')
                ;
                $expDate =
                    $this->http->FindSingleNode("//div[header[normalize-space(.)='My Rewards Dollars']]/following-sibling::div/p[@class='AccountBalancePage-balance-expiration']", null, false, '/Expires\s+(.+)/')
                    ?? $this->http->FindSingleNode('//span[contains(text(), "Rewards Expire On:")]/following-sibling::span[1]')
                ;
            }
            // not a member
            if (!isset($balance) && $this->http->FindNodes("//a[@href = 'https://www.wayfair.com/session/secure/account/rewards_signup.php']/@href")) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        } else { // Wayfair Rewards (Expires {date})
            $expDate = Html::cleanXMLValue($this->http->FindPreg("/Rewards\s*\(Exp\.\s*([^\)]+)/ims"));
        }

        if (strtotime($expDate) !== false) {
            $this->SetExpirationDate(strtotime($expDate));
        }
        // Balance - Wayfair Rewards
        $this->SetBalance($balance);

        // refs #20907
        $creditCardRewards =
            $this->http->FindSingleNode("//div[header[normalize-space(.)='My Wayfair credit card Rewards']]/following-sibling::div//p[@class='AccountBalancePage-balance-amount']")
            ?? $response->data->me->customer->wayfairCardRewards->currentRewardsDollars
            ?? null
        ;

        if (isset($creditCardRewards)) {
            $this->AddSubAccount([
                'Code'        => "CreditCardRewards",
                'DisplayName' => "Credit Card Rewards",
                'Balance'     => $creditCardRewards,
            ]);
        }

        $this->http->GetURL("https://www.wayfair.com/session/secure/account/personal_info_edit.php?");
        // set Name property
        $this->SetProperty("Name", beautifulName(trim(
            $this->http->FindSingleNode('//p[@data-enzyme-id="account-name-open-close-text" and normalize-space() != "Add your name"]') ??
            $this->http->FindSingleNode("//input[@name='first_name' or @name = 'firstName']/@value") . ' ' .
            $this->http->FindSingleNode("//input[@name='last_name' or @name = 'lastName']/@value")
        )));
    }

    protected function reCaptcha()
    {
        $this->logger->notice(__METHOD__);
        // reCaptcha
        if ($this->http->ParseForm(null, "//form[@id = 'bd' and @class = 'Captcha']")) {
            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }

            $vid = '9ef38e0e-3f8b-11eb-ab47-0242ac120018'; //$this->http->FindPreg("/var vid = \"([^\"]+)/");
            $uuid = $this->http->FindPreg("/window._pxUuid = \"([^\"]+)/");

            $this->http->setCookie("_pxCaptcha", $captcha . ":{$vid}:{$uuid}", date("D, d M Y H:i:s e"));

            sleep(1);
            $this->http->GetURL("https://www.wayfair.com/session/secure/account/login.php?");
//            $this->http->SetInputValue('g-recaptcha-response', $captcha);
//            $this->http->PostForm();
        }

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'bd' and @class = 'Captcha']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            $key = '6Lcj-R8TAAAAABs3FrRPuQhLMbp5QrHsHufzLf7b';
        }

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function parseQuestion($response)
    {
        $this->logger->notice(__METHOD__);

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $data = json_encode([
            'email'       => $this->AccountFields['Login'],
            '_csrf_token' => $this->State['csrfToken'],
        ]);
        $headers = [
            "Accept"                => "application/json",
            "Content-Type"          => "application/json",
            "x-auth-caller-context" => "auth_main_page_context",
            "x-csn-csrf-token"      => $this->State['csrfCsn'],
            "x-parent-txid"         => $this->State['txid'],
            "X-Requested-With"      => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.wayfair.com/auth/v2/email/challenge", $data, $headers);
        $this->http->RetryCount = 2;
        $result = $this->http->JsonLog();

        $this->AskQuestion("Enter the Code We Emailed You {$response->email}. Your code will expire after 10 minutes.", null, 'Question');

        return !($result === null);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        /*
        if ($this->http->FindPreg("/Sign Out/ims")) {
        */
        if ($this->http->FindSingleNode("
                (//div[contains(text(), 'Account Balance')] | //p[contains(text(), 'Account Balance')])[1]
                | //div[header[normalize-space(.)='My Rewards Dollars' or normalize-space(.)='My Reward Dollars']]/following-sibling::div/p[@class='AccountBalancePage-balance-amount']
            ")
        ) {
            return true;
        }

        return false;
    }

    private function seleniumAuth($seleniumAuth = false)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_59);
            $selenium->setKeepProfile(true);
            //$selenium->disableImages();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->http->GetURL("https://www.wayfair.com/session/secure/account/login.php");

            $cookies = $selenium->driver->manage()->getCookies();

            if ($seleniumAuth === true) {
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@autocomplete = "username"]'), 0);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-hb-id = "LoadingButton"]'), 0);
                // save page to logs
                $this->savePageToLogs($selenium);

                if (!$loginInput || !$button) {
                    return $this->checkErrors();
                }

                $this->logger->debug("set login");
                $loginInput->sendKeys($this->AccountFields['Login']);
                $button->click();

                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@autocomplete = "current-password"]'), 5);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-hb-id = "LoadingButton"]'), 0);
                // save page to logs
                $this->savePageToLogs($selenium);

                if (!$passwordInput || !$button) {
                    $message = $selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "To protect your information, your account has been temporarily locked. Please reset your password to regain access to your account.")]'), 0);

                    if ($message) {
                        throw new CheckException("We're sorry, but your account has been temporarily locked out. To access your account, please reset your password.", ACCOUNT_LOCKOUT);
                    }

                    if ($this->http->FindSingleNode('//h1[contains(text(), "Please Create a Password to Continue")]')) {
                        throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    return $this->checkErrors();
                }

                $passwordInput->sendKeys($this->AccountFields['Pass']);
                $button->click();

                $selenium->waitForElement(WebDriverBy::xpath('
                    //p[contains(@class, "InputValidationText")]
                    | //span[
                        contains(text(), "Skip Confirmation")
                        or contains(text(), "No Thanks")
                        or contains(text(), "Maybe Later")
                    ]
                    | (//div[contains(text(), \'Account Balance:\')] | //p[contains(text(), \'Account Balance:\')])[1]
                    | //div[header[normalize-space(.)=\'My Rewards Dollars\']]/following-sibling::div/p[@class=\'AccountBalancePage-balance-amount\']
                '), 10);
                $this->savePageToLogs($selenium);

                if ($skip = $selenium->waitForElement(WebDriverBy::xpath('
                        //span[
                            contains(text(), "Skip Confirmation")
                            or contains(text(), "No Thanks")
                            or contains(text(), "Maybe Later")
                        ]
                    '), 0)
                ) {
                    $skip->click();

                    $selenium->waitForElement(WebDriverBy::xpath('(//div[contains(text(), \'Account Balance:\')] | //p[contains(text(), \'Account Balance:\')])[1]'), 5);
                    $this->savePageToLogs($selenium);
                }

                if ($skip = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Maybe Later")]'), 0)) {
                    $skip->click();
                    $selenium->waitForElement(WebDriverBy::xpath('(//div[contains(text(), \'Account Balance:\')] | //p[contains(text(), \'Account Balance:\')])[1]'), 5);
                    $this->savePageToLogs($selenium);
                }

                if ($selenium->waitForElement(WebDriverBy::xpath('(//div[contains(text(), \'Account Balance:\')] | //p[contains(text(), \'Account Balance:\')])[1]'), 0)) {
                    $this->savePageToLogs($selenium);

                    $selenium->http->GetURL('https://www.wayfair.com/v/account/rewards/balance');
                    $this->savePageToLogs($selenium);

                    $balance = $this->http->FindSingleNode("//strong[@id = 'earned_rewards_balance_header']");
                    // if offer in header was found
                    // My Rewards Dollars - new design?
                    if (!isset($balance)) {
                        $balance =
                            $this->http->FindSingleNode("//div[header[normalize-space(.)='My Rewards Dollars']]/following-sibling::div/p[@class='AccountBalancePage-balance-amount']")
                            ?? $this->http->FindSingleNode('//div[contains(@class, "rewards_balance xltitle")]')
                            ?? $this->http->FindSingleNode('//div[contains(@class, "rewards_balance xltitle")]')
                        ;
                        $expDate =
                            $this->http->FindSingleNode("//div[header[normalize-space(.)='My Rewards Dollars']]/following-sibling::div/p[@class='AccountBalancePage-balance-expiration']", null, false, '/Expires\s+(.+)/')
                            ?? $this->http->FindSingleNode('//span[contains(text(), "Rewards Expire On:")]/following-sibling::span[1]')
                        ;
                    } elseif (!isset($balance)) {
                        $balance = $this->http->FindSingleNode("//p[contains(@class, 'ProductReviewBanner-balance')]/text()[1]");

                        if ($selenium->http->currentUrl() != self::REWARDS_PAGE_URL) {
                            $selenium->http->GetURL(self::REWARDS_PAGE_URL);
                            $this->savePageToLogs($selenium);
                        }
                        // Rewards Expire On
                        $expDate = $this->http->FindSingleNode("//ul[li[contains(text(), 'Rewards balance')]]/following-sibling::p[contains(text(), 'Expires')]", null, true, "/\:\s*([^<]+)/");
                        // Wayfair Rewards Balance
                        if (!isset($balance)) {
                            $balance = $this->http->FindSingleNode("//ul[li[contains(text(), 'Rewards balance')]]/following-sibling::p[contains(@class, 'PlccAccount-rewards-amount')]");
                        }
//                $balance = $this->http->FindSingleNode("//div[contains(text(), 'Wayfair Rewards Balance')]/following-sibling::div[1]");
                        // My Rewards Dollars - new design?
                        if (!isset($balance)) {
                            $balance =
                                $this->http->FindSingleNode("//div[header[normalize-space(.)='My Rewards Dollars' or normalize-space(.)='My Reward Dollars']]/following-sibling::div/p[@class='AccountBalancePage-balance-amount']")
                                ?? $this->http->FindSingleNode('//div[contains(@class, "rewards_balance xltitle")]')
                            ;
                            $expDate =
                                $this->http->FindSingleNode("//div[header[normalize-space(.)='My Rewards Dollars']]/following-sibling::div/p[@class='AccountBalancePage-balance-expiration']", null, false, '/Expires\s+(.+)/')
                                ?? $this->http->FindSingleNode('//span[contains(text(), "Rewards Expire On:")]/following-sibling::span[1]')
                            ;
                        }
                        // not a member
                        if (!isset($balance) && $this->http->FindNodes("//a[@href = 'https://www.wayfair.com/session/secure/account/rewards_signup.php']/@href")) {
                            $this->SetWarning(self::NOT_MEMBER_MSG);
                        }
                    } else { // Wayfair Rewards (Expires {date})
                        $expDate = Html::cleanXMLValue($this->http->FindPreg("/Rewards\s*\(Exp\.\s*([^\)]+)/ims"));
                    }

                    if (strtotime($expDate) !== false) {
                        $this->SetExpirationDate(strtotime($expDate));
                    }
                    // Balance - Wayfair Rewards
                    $this->SetBalance($balance);

                    // refs #20907
                    $creditCardRewards = $this->http->FindSingleNode("//div[header[normalize-space(.)='My Wayfair credit card Rewards']]/following-sibling::div//p[@class='AccountBalancePage-balance-amount']");

                    if (isset($creditCardRewards)) {
                        $this->AddSubAccount([
                            'Code'        => "CreditCardRewards",
                            'DisplayName' => "Credit Card Rewards",
                            'Balance'     => $creditCardRewards,
                        ]);
                    }

                    $selenium->http->GetURL("https://www.wayfair.com/session/secure/account/personal_info_edit.php?");
                    $this->savePageToLogs($selenium);
                    // set Name property
                    $this->SetProperty("Name", beautifulName(trim(
                        $this->http->FindSingleNode('//p[@data-enzyme-id="account-name-open-close-text" and normalize-space() != "Add your name"]') ??
                        $this->http->FindSingleNode("//input[@name='first_name' or @name = 'firstName']/@value") . ' ' .
                        $this->http->FindSingleNode("//input[@name='last_name' or @name = 'lastName']/@value")
                    )));
                } elseif ($message = $this->http->FindSingleNode('//p[contains(@class, "InputValidationText")]')) {
                    $this->logger->error("[Error]: {$message}");

                    if (strstr($message, 'Password is incorrect. Please try again.')) {
                        throw new CheckException('Password is incorrect. Please try again.', ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;
                }
            }

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return false;
    }
}
