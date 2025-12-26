<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSaveonmore extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
//        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.morerewards.ca/your-account', [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.morerewards.ca/login");

        return $this->selenium();

        if ($this->http->FindSingleNode('//title[contains(text(), "Access rights validated")]')) {
            $this->http->GetURL("https://account.morerewards.ca/?spEntityID=www.morerewards.ca&goto=https://login.morerewards.ca:443/id/saml2/continue/metaAlias/customers/morerewards-api/idp?secondVisitUrl%3D/id/SSORedirect/metaAlias/customers/morerewards-api/idp?ReqID%253DONELOGIN_666d893baf2c013dcc10e6bd6e90fc42ca8d0e32&AMAuthCookie=");

            $data = [
                "client_id" => "MoreRewardsOAuth",
                "token"     => "eyJ0eXAiOiJKV1QiLCJraWQiOiJxek9wNWtHWnRTMXFYdjE5YjNmL05YMS8zQmM9IiwiYWxnIjoiUlMyNTYifQ.eyJzdWIiOiJkYXZpZEBlZGdlcnRvbi5jby51ayIsImN0cyI6Ik9BVVRIMl9TVEFURUxFU1NfR1JBTlQiLCJhdXRoX2xldmVsIjowLCJhdWRpdFRyYWNraW5nSWQiOiJmZDZiZjNhNS05ZTE0LTRkZjEtOWRlMC0xZjgzMTdlNmM2ZmEtNzE0ODkxIiwic3VibmFtZSI6ImRhdmlkQGVkZ2VydG9uLmNvLnVrIiwiaXNzIjoiaHR0cHM6Ly9sb2dpbi5tb3JlcmV3YXJkcy5jYTo0NDMvaWQvb2F1dGgyL3JlYWxtcy9yb290L3JlYWxtcy9jdXN0b21lcnMvcmVhbG1zL21vcmVyZXdhcmRzLWFwaSIsInRva2VuTmFtZSI6ImFjY2Vzc190b2tlbiIsInRva2VuX3R5cGUiOiJCZWFyZXIiLCJhdXRoR3JhbnRJZCI6Inh5cWg5OE5hRGd6TmVvWW5OWmIzZ1pLUDdWdyIsImF1ZCI6Ik1vcmVSZXdhcmRzT0F1dGgiLCJuYmYiOjE2NjkxODg0MzgsImdyYW50X3R5cGUiOiJhdXRob3JpemF0aW9uX2NvZGUiLCJzY29wZSI6WyJlbWFpbCJdLCJhdXRoX3RpbWUiOjE2NjkxODg0MzYsInJlYWxtIjoiL2N1c3RvbWVycy9tb3JlcmV3YXJkcy1hcGkiLCJleHAiOjE2NjkxOTIwMzgsImlhdCI6MTY2OTE4ODQzOCwiZXhwaXJlc19pbiI6MzYwMCwianRpIjoiXzJPamhFcXBMVEgwMzZUdDlBSWhaYzJjb1pJIiwibWFpbCI6ImRhdmlkQGVkZ2VydG9uLmNvLnVrIiwicHVibGljUmVmZXJlbmNlSWQiOiIxMjQ1NTU0OSIsImZpcnN0TmFtZSI6IkRBVklEIiwibGFzdE5hbWUiOiJFREdFUlRPTiIsIm1vcmVSZXdhcmRzQ2FyZCI6IjQ4MDEyMDUyOTA3In0.I1flXGHAm-KE76yM01VWPrua6D5vOiNuw5FxPnqia65tVs69nfZUddhfs9d6lrOqo8w_PA6P1hPjaVJorrRSyc569ekp_6SiyCagpJmeIM4PevGtwhxbqpyCZEQWoso5F59KzO7nrDn_7OxjJAavebbwVzN4EsuE6LfeRyIVD5zUm7DFT9bNDn8hkLMVgL2iHiWAL14eWLappH7rugyCRr8x3iF73k1scmyXx5it6uywI4pwjHUxkQZwybTaY2p657Y9vbfgnWhb1WBnT4o1SLkEXFEBftYeDJsCn7uQ0gC4S3N2wgttGB3LqwURkM_bTq-FsKiV_Q0Y528_IjhLyg",
            ];
            $this->http->PostURL("https://login.morerewards.ca/id/oauth2/morerewards/token/revoke", $data);

            $this->http->PostURL("https://login.morerewards.ca/id/json/morerewards/authenticate?authIndexType=service&authIndexValue=MoreRewardsAPIAuthTreeV2", "");
        }

        $response = $this->http->JsonLog();

        if (!isset($response->authId)) {
            return $this->checkErrors();
        }

        $authId = $response->authId;

        $data = [
            "authId"    => $authId,
            "callbacks" => [
                [
                    "type"   => "NameCallback",
                    "output" => [
                        [
                            "name"  => "prompt",
                            "value" => "User Name",
                        ],
                    ],
                    "input"  => [
                        [
                            "name"  => "IDToken1",
                            "value" => $this->AccountFields['Login'],
                        ],
                    ],
                    "_id"    => 0,
                ],
                [
                    "type"   => "PasswordCallback",
                    "output" => [
                        [
                            "name"  => "prompt",
                            "value" => "Password",
                        ],
                    ],
                    "input"  => [
                        [
                            "name"  => "IDToken2",
                            "value" => $this->AccountFields['Pass'],
                        ],
                    ],
                    "_id"    => 1,
                ],
            ],
            "status"    => 200,
            "ok"        => true,
        ];

        $headers = [
            "Accept"             => "application/json",
            "accept-api-version" => "protocol=1.0,resource=2.1",
            "content-type"       => "application/json",
            "x-requested-with"   => "forgerock-sdk",
            "Origin"             => "https://account.morerewards.ca",
        ];
        $this->http->RetryCount = 0;
        sleep(3);
//        $this->http->PostURL("https://login.morerewards.ca/id/json/morerewards/authenticate?authIndexType=service&authIndexValue=MoreRewardsAPIAuthTreeV2", json_encode($data), $headers);
        $data = '{"authId":"' . $authId . '","callbacks":[{"type":"NameCallback","output":[{"name":"prompt","value":"User Name"}],"input":[{"name":"IDToken1","value":"' . $this->AccountFields['Login'] . '"}],"_id":0},{"type":"PasswordCallback","output":[{"name":"prompt","value":"Password"}],"input":[{"name":"IDToken2","value":"' . $this->AccountFields['Pass'] . '"}],"_id":1}],"status":200,"ok":true}';
        $this->http->PostURL("https://login.morerewards.ca/id/json/morerewards/authenticate?authIndexType=service&authIndexValue=MoreRewardsAPIAuthTreeV2", $data, $headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();

        $this->http->GetURL("https://login.morerewards.ca/id/oauth2/morerewards/authorize?client_id=MoreRewardsOAuth&redirect_uri=https%3A%2F%2Faccount.morerewards.ca%2Fcallback&response_type=code&scope=email&state=MTkxNzQxMzIxMzU4MTUyMzI2NjIwMzE2NjI0MjE4NDE3MjE2MjE3MjQ&code_challenge=kHkVP7lXdlDdA6l7BeVzUNZ_C34jCI27jJcy1kNwZ5U&code_challenge_method=S256");
        /*
        if ($this->http->FindPreg('/window\["bobcmn"\]\s*=/')) {
            $this->selenium();
        }

        if ($this->http->FindPreg('/window\["bobcmn"\]\s*=/')) {
            throw new CheckRetryNeededException(2);
        }

        if (!$this->http->ParseForm("user-login-form")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("user_login_name", $this->AccountFields['Login']);
        $this->http->SetInputValue("user_login_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("op", 'login');
        $this->http->SetInputValue('remember_user_checkbox', 1);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry, More Rewards ID sign-in is currently under maintenance, we'll be back soon.
        if ($message = $this->http->FindSingleNode('//*[self::p or self::h2][contains(text(), "Sorry, More Rewards ID sign-in is currently under maintenance, we")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//*[self::p or self::h2][contains(text(), "More Rewards is currently under maintenance. We will be back soon.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // More Rewards under maintenance
        if ($message = $this->http->FindSingleNode("//title[contains(text(), 'More Rewards under maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The page you requested is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The page you requested is temporarily unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindPreg('/window\["bobcmn"\]\s*=/')) {
            $this->http->PostURL("https://www.morerewards.ca/your-account", []);

            // it works
            if ($this->http->FindPreg('/window\["bobcmn"\]\s*=/')) {
                throw new CheckRetryNeededException(2);
            }
        }
        */

        // Invalid credentials
        if ($message =
                $this->http->FindSingleNode('//p[contains(text(), "Email or password is incorrect.")]')
                ?? $this->http->FindPreg("/(Account does not exist. Please try again later\.)/ims")
                ?? $this->http->FindPreg("/(You have entered an invalid email or password. Please try again\.)/ims")
                ?? $this->http->FindPreg("/(Given email address does not exist. Please create a new account\.)/ims")
                ?? $this->http->FindPreg("/(You have entered an invalid email or password\. Please try again\.)/ims")
                ?? $this->http->FindPreg("/(You must reset your password to continue\.) To reset now please/ims")
        ) {
            $this->captchaReporting($this->recognizer);
            /*
            if (isset($this->recognizer)) {
                $this->sendNotification('refs #24888 saveonmore - captcha success // IZ');
            }
            */
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account is locked.
        if ($message = $this->http->FindPreg("/(Your account is locked\.)/ims")) {
            $this->captchaReporting($this->recognizer);
            /*
            if (isset($this->recognizer)) {
                $this->sendNotification('refs #24888 saveonmore - captcha success // IZ');
            }
            */
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // Unable to get account details. Please try again later.
        if ($message = $this->http->FindPreg("/(Unable to get account details\.\s*Please try again later\.)/ims")
            // We are having technical problems
            ?? $this->http->FindPreg("/(We are having technical problems\. Please try again later\.)/ims")
            ?? $this->http->FindSingleNode('//p[contains(text(), "Error when logging in.")]')
            /*
             * To protect your personal information and your valuable More Rewards points,you will be required to change your password.
             * An email with the link to create a new password has been sent to your email registered with More Rewards.
             */
//            ?? $this->http->FindSingleNode("//b[contains(text(), 'To protect your personal information and your valuable More Rewards points, you will be required to change your password.')]")
        ) {
            $this->captchaReporting($this->recognizer);
            /*
            if (isset($this->recognizer)) {
                $this->sendNotification('refs #24888 saveonmore - captcha success // IZ');
            }
            */
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        /*
        if ($this->parseQuestion()) {
            $this->captchaReporting($this->recognizer);

            return false;
        }
        */

        // Access is allowed
        if ($this->http->FindSingleNode("//a[@id = 'logout'] | //button[contains(text(), 'Sign Out')] | //h3[contains(text(), 'Card Number')]/following-sibling::span")) {
            $this->captchaReporting($this->recognizer);
            /*
            if (isset($this->recognizer)) {
                $this->sendNotification('refs #24888 saveonmore - captcha success // IZ');
            }
            */
            return true;
        }

        // ReCAPTCHA verification failed. Please try again.
        if ($this->http->FindSingleNode('//div[@class="msg error-msg"]/p[contains(text(), "ReCAPTCHA verification failed. Please try again.")]')) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException();
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm('one-time-password-form')) {
            return false;
        }

        $this->Question = "Please enter the 6-digit password specified in the email to the text box down below.";
        $this->Step = "Question";
        $this->ErrorCode = ACCOUNT_QUESTION;

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->SetInputValue('user_login_password', $this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class,'type-error')]//text()[contains(., 'You have entered an invalid email or password. Please try again.')]")) {
            $this->AskQuestion($this->Question, $message);

            return false;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class,'type-error')]//text()[contains(., 'Your account is locked. Please try again ')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($this->http->FindPreg('/window\["bobcmn"\]\s*=/')) {
            $this->http->PostURL("https://www.morerewards.ca/your-account", []);
        }

        return true;
    }

    public function Parse()
    {
        // Balance - points
        $this->SetBalance(
            $this->http->FindSingleNode("//div[h3[contains(text(), 'You have')]]/span", null, true, "/(.+)\s+pts/")
            ?? $this->http->FindSingleNode("//span[@id = 'user-points']/strong")
        );
        // Name
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//*[@aria-label = 'First name']/@value")
            . ' ' . $this->http->FindSingleNode("//*[@aria-label = 'Last name']/@value"));

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//span[@id = 'user-name']/strong");
        }

        $this->SetProperty("Name", beautifulName($name));
        // More Rewards card number
        $this->SetProperty("Number", $this->http->FindSingleNode("//h3[contains(text(), 'Card Number')]/following::span[1]"));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6Le7HbYiAAAAAMV1ziIHuZJ2Ajk9bi743ks98PWW';

        $postData = [
            "type"              => "RecaptchaV2TaskProxyless",
            "websiteURL"        => $this->http->currentUrl(),
            "websiteKey"        => $key,
            "isEnterprise"      => false,
            "recaptchaV2Normal" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    /*
    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//div[@id = "html_element"]//iframe[@title = "reCAPTCHA"]/@src', null, true, '/&k=(\w+)/');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => 'https://account.morerewards.ca/?goto=/userPage/home',
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
    */

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;

        try {
            $selenium->UseSelenium();

            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            /*
            $this->seleniumOptions->addAntiCaptchaExtension = true;
            $this->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
            */
            $selenium->http->setUserAgent($this->http->userAgent);

            $selenium->setProxyGoProxies();

            /*
            if ($this->attempt > 0) {
                $selenium->setProxyGoProxies();
            }
            */

            $selenium->disableImages();
            $selenium->useCache();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->http->GetURL("https://account.morerewards.ca/?goto=/userPage/home");

            $login = $selenium->waitForElement(WebDriverBy::id('email'), 10);
            $pass = $selenium->waitForElement(WebDriverBy::id('password'), 0);
            $submitButton = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In") or contains(text(), "Sign in")]'), 0);
            $this->savePageToLogs($selenium);

            if (empty($login) || empty($pass) || empty($submitButton)) {
                $this->logger->error('something went wrong');

                if ($this->http->FindSingleNode('//h2[contains(text(), "For your protection this endpoint is blocked")]')) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return $this->checkErrors();
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $this->logger->debug("clicking submit");
            sleep(3); // possible solution for no result at all after click
            $submitButton->click();

            /*// ReCaptcha
            $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class,'antigate_solver recaptcha solved')]"), 80);
            $submitButton = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In") or contains(text(), "Sign in")]'), 0);
            $submitButton->click();*/

            $res = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "logout"] | //button[contains(text(), "Sign Out")] | //p[contains(text(), "Error loading this page")] | //p[contains(text(), "Error when logging in.") or contains(text(), "Email or password is incorrect.")] | //h3[contains(text(), "Card Number")]/following-sibling::span | //button[contains(text(), "Update Later")] | //div[@id = "notistack-snackbar"]/p'), 10);
            $this->savePageToLogs($selenium);

            // skip profile update
            if ($updBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Update Later")]'), 0)) {
                $updBtn->click();

                $res = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "logout"] | //button[contains(text(), "Sign Out")] | //p[contains(text(), "Error loading this page")] | //p[contains(text(), "Error when logging in.") or contains(text(), "Email or password is incorrect.")] | //h3[contains(text(), "Card Number")]/following-sibling::span | //div[@id = "notistack-snackbar"]/p'), 10);
            }

            if (
                !$res
                && ($captcha = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "html_element"]//iframe[@title = "reCAPTCHA"]'), 0))
            ) {
                /*
                $retry = true;
                */

                /* TODO: mot working!
                $this->logger->error('recaptcha triggered');
                $answer = $this->parseCaptcha();
                $selenium->driver->executeScript("
                    document.getElementById('g-recaptcha-response').innerHTML = '$answer';
                    document.querySelector('iframe[title=reCAPTCHA]').remove();
                ");
                $this->logger->debug("clicking submit");
                $submitButton->click();

                $res = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "logout"] | //button[contains(text(), "Sign Out")] | //p[contains(text(), "Error loading this page")] | //p[contains(text(), "Error when logging in.") or contains(text(), "Email or password is incorrect.")]'), 10);
                $this->savePageToLogs($selenium);
                */

                $captcha = $this->parseCaptcha();

                $this->logger->notice("Executing captcha callback");

                $selenium->driver->executeScript('
                    var findCb = (object) => {
                        if (!!object["callback"] && !!object["sitekey"]) {
                            return object["callback"]
                        } else {
                            for (let key in object) {
                                if (typeof object[key] == "object") {
                                    return findCb(object[key])
                                } else {
                                    return null
                                }
                            }
                        }
                    }
                    findCb(___grecaptcha_cfg.clients[0])("' . $captcha . '");
                ');

                sleep(2);
                $this->savePageToLogs($selenium);
                $this->logger->notice("Removing captcha iframe");

                $selenium->driver->executeScript('
                    document.getElementById("g-recaptcha-response").innerHTML = "' . $captcha . '";
                    document.querySelector("iframe[title=reCAPTCHA]").remove();
                ');

                sleep(2);
                $this->savePageToLogs($selenium);
                $this->logger->debug("clicking submit");

                $submitButton = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In") or contains(text(), "Sign in")]'), 0);
                $submitButton->click();

                $res = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "logout"] | //button[contains(text(), "Sign Out")] | //p[contains(text(), "Error loading this page")] | //p[contains(text(), "Error when logging in.") or contains(text(), "Email or password is incorrect.")]'), 10);
                $this->savePageToLogs($selenium);

                /*
                if (!$res) {
                    $retry = true;
                }
                */
            }

            // AccountID: 3620112
            if ($this->http->FindSingleNode('//p[contains(text(), "Error loading this page")]')) {
                $selenium->http->GetURL("https://www.morerewards.ca/your-account");
                $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "logout"] | //button[contains(text(), "Sign Out")] | //h3[contains(text(), "Card Number")]/following-sibling::span'), 10);
            }
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }
}
