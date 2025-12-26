<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerKiva extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    /** @var CaptchaRecognizer */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'Kiva')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function delay()
    {
        $this->logger->notice(__METHOD__);
        $delay = rand(1, 10);
        $this->logger->debug("Delay -> {$delay}");
        sleep($delay);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.kiva.org/portfolio", [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            $this->delay();

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.kiva.org/login");
        $this->delay();
        $this->captchaWorkaround();

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug($currentUrl);
        $client_id = $this->http->FindPreg("/client=([^&]+)/", false, $currentUrl);
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $currentUrl);
        $scope = $this->http->FindPreg("/scope=([^&]+)/", false, $currentUrl);

        if (!$client_id || !$state || !$scope) {
            if (isset($this->http->Response['code']) && $this->http->Response['code'] == 403) {
                $this->DebugInfo = "captcha";
                $this->selenium();
                $this->Login();

                return false;
            }

            return $this->checkErrors();
        }

        $data = [
            "client_id"     => $client_id,
            "redirect_uri"  => "https://www.kiva.org/authenticate/process",
            "tenant"        => "kiva-prod",
            "response_type" => "code",
            "scope"         => $scope,
            "audience"      => "https://kiva-prod.auth0.com/userinfo",
            "_csrf"         =>
                $this->http->getCookieByName("_csrf", 'kiva-prod.auth0.com', '/usernamepassword/login', true)
                ?? $this->http->getCookieByName("_csrf", 'login.kiva.org', '/usernamepassword/login', true),
            "state"         => $state,
            "_intstate"     => "deprecated",
            "username"      => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "connection"    => "Username-Password-Authentication",
        ];
        $headers = [
            "Auth0-Client" => "eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTQuMyJ9",
            "Content-Type" => "application/json",
            "Accept"       => "*/*",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://login.kiva.org/usernamepassword/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg('/"invalid_captcha","code":"invalid_captcha"\}/')) {
            $this->http->PostURL("https://login.kiva.org/usernamepassword/challenge", '{"state":"' . $state . '"}', $headers);
            $response = $this->http->JsonLog();

            if (isset($response->provider, $response->siteKey) && $response->provider == 'recaptcha_enterprise') {
                $data['captcha'] = $this->parseCaptcha($response->siteKey);
            } elseif (isset($response->image)) {
                $data['captcha'] = $this->parseCaptchaImg($response->image);
            }

            if (empty($data['captcha'])) {
                return false;
            }

            $this->http->RetryCount = 0;
            $this->http->PostURL("https://login.kiva.org/usernamepassword/login", json_encode($data), $headers);
            $this->http->RetryCount = 2;
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're likely performing routine maintenance
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re likely performing routine maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'This is likely just routine maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Thank you for your interest in Kiva!
         * There are so many people who want to help entrepreneurs work towards their dreams
         * that we're experiencing some technical difficulties at the moment.
         */
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Thank you for your interest in Kiva! There are so many people who want to help entrepreneurs work towards their dreams that we\'re experiencing some technical difficulties at the moment.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The server returned an invalid or incomplete response.
        if ($message = $this->http->FindPreg('/(The server returned an invalid or incomplete response\.)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->setMaxRedirects(10);
        $this->http->ParseForm("hiddenform");

        if ($this->http->Response['code'] != 403 && !$this->http->PostForm() && !in_array($this->http->Response['code'], [403, 401])) {
            return $this->checkErrors();
        }

        $this->http->setMaxRedirects(5);

        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->http->FindSingleNode("//p[contains(text(), 'To finish creating your account, please enter your first and last name below.')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->currentUrl() == 'https://www.kiva.org/') {
            $this->http->GetURL("https://www.kiva.org/api/session");
            $this->http->JsonLog();

            if ($this->http->FindPreg("/^true$/")) {
                return true;
            }
        }

        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        $response = $this->http->JsonLog();
        $description = $response->description ?? null;

        if ($description) {
            $this->logger->error("[Error]: {$description}");

            // Username or password are invalid.
            if (in_array($description, ["Wrong email or password.", "Password does not match entry in kiva db"])) {
                $this->captchaReporting($this->recognizer, true);

                throw new CheckException("Username or password are invalid.", ACCOUNT_INVALID_PASSWORD);
            }

            if (in_array($description, [
                "This login attempt has been blocked because the password you're using was previously disclosed through a data breach (not in this application). Please check your email for more information.",
                "Your account has been blocked after multiple consecutive login attempts. We've sent you an email with instructions on how to unblock it.",
            ])
            ) {
                $this->captchaReporting($this->recognizer, true);

                throw new CheckException("We have detected a potential security issue with this account. To protect your account, we have blocked this login. An email was sent with instruction on how to unblock your account. ", ACCOUNT_LOCKOUT);
            }

            if ($description == 'Invalid captcha value') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0);
            }

            $this->DebugInfo = $description;

            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->http->SetInputValue('code', $answer);
        $this->http->SetInputValue('rememberBrowser', "true");

        if (!$this->http->PostForm()) {
            return false;
        }

        $error = $this->http->FindSingleNode('//li[@id = "error-element-code"]');
        $this->logger->error("[Error]: {$error}");

        if (strstr($error, 'OTP Code must have 6 numeric characters.')) {
            $this->AskQuestion($this->Question, $error);

            return false;
        }

        return true;
    }

    public function Parse()
    {
        // get access_token for graphql
        $this->http->GetURL("https://login.kiva.org/authorize?client_id=AEnMbebwn6LBvxg1iMYczZKoAgdUt37K&response_type=token%20id_token&redirect_uri=https%3A%2F%2Fwww.kiva.org%2Fprocess-browser-auth&scope=openid%20mfa&audience=https%3A%2F%2Fapi.kivaws.org%2Fgraphql&state=tQycFbMnyYrVHb1gl6nzVFS7ootnwXIZ&nonce=jmtGAb63wR7mDLlD.6p8lK5mNSy-G1DE&response_mode=web_message&prompt=none&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xOS4yIn0%3D");

        $access_token = $this->http->FindPreg('/"access_token":"([^\"]+)/ims');

        if (!$access_token) {
            return;
        }

        $basketId = urldecode($this->http->getCookieByName('kvbskt', "www.kiva.org", "/", true));
        $data = '{"operationName":"accountOverview","variables":{},"query":"query accountOverview {\n  my {\n    id\n    userStats {\n      amount_outstanding\n      __typename\n    }\n    userAccount {\n      id\n      balance\n      promoBalance\n      __typename\n    }\n    __typename\n  }\n}\n"}';

        $headers = [
            'Accept'        => '*/*',
            'Content-Type'  => 'application/json',
            'origin'        => 'https://www.kiva.org',
            'Authorization' => "Bearer {$access_token}", // from https://login.kiva.org/authorize?client_id=...
        ];
        $this->http->PostURL("https://marketplace-api.k1.kiva.org/graphql", $data, $headers);
        $response = $this->http->JsonLog();
        // Balance - Available Kiva Credit
        $this->SetBalance($response->data->my->userAccount->balance);
        // Outstanding Loans
        $this->SetProperty("OutstandingLoans", "$" . $response->data->my->userStats->amount_outstanding);

        $data = '{"operationName":"lendingInsights","variables":{},"query":"query lendingInsights {\n  my {\n    id\n    lendingStats {\n      id\n      amountLentPercentile\n      lentTo {\n        countries {\n          totalCount\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    userStats {\n      amount_of_loans\n      number_of_loans\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->PostURL("https://marketplace-api.k1.kiva.org/graphql", $data, $headers);
        $response = $this->http->JsonLog();
        // Loans
        $this->SetProperty("LoansMade", "$" . $response->data->my->userStats->amount_of_loans);
        // Loans made by my invitees
        $this->SetProperty("MyInvitees", $response->data->my->userStats->number_of_loans);

        // Bonus
        $balance = $this->http->FindSingleNode("(//div[contains(@class, 'availableCredit')]/div[@class = 'number'])[2]", null, true, "/([\d\.\,]+)/ims");
        $exp = $this->http->FindSingleNode("(//div[contains(@class, 'availableCredit')]/div[@class = 'number'])[2]/preceding-sibling::div/a[@class = 'expiresInfo']", null, true, "/Expires\s*([^<]+)/ims");

        if (isset($balance, $exp) && strtotime($exp)) {
            $this->AddSubAccount([
                'Code'           => 'kivaBonus',
                'DisplayName'    => "Bonus",
                'Balance'        => $balance,
                'ExpirationDate' => strtotime($exp),
            ]);
        }// if (isset($balance, $exp) && strtotime($exp))

        $this->delay();
        $this->http->GetURL("https://www.kiva.org/portfolio/loans");
        $path = "//div[@class = 'my-loans-lent-stats']";
        // Fundraising
        $this->SetProperty("Fundraising", $this->http->FindSingleNode("$path//span[contains(text(), 'Fundraising')]/following-sibling::span"));
        // Funded
        $this->SetProperty("Funded", $this->http->FindSingleNode("$path//span[contains(text(), 'Funded')]/following-sibling::span"));
        // Paying back
        $this->SetProperty("PayingBack", $this->http->FindSingleNode("$path//span[normalize-space(text()) = 'Fundraising']/following-sibling::span"));
        // Paying back delinquent
        $this->SetProperty("PaidBack", $this->http->FindSingleNode("$path//span[contains(text(), 'Paying back delinquent')]/following-sibling::span"));
        // Repaid
        $this->SetProperty("Repaid", $this->http->FindSingleNode("$path//span[normalize-space(text()) = 'Repaid']/following-sibling::span"));
        // Repaid with currency loss
        $this->SetProperty("RepaidWithLoss", $this->http->FindSingleNode("$path//span[contains(text(), 'Repaid with currency loss')]/following-sibling::span"));
        // Ended in default
        $this->SetProperty("EndedWithLoss", $this->http->FindSingleNode("$path//span[contains(text(), 'Ended in default')]/following-sibling::span"));
        // Refunded
        $this->SetProperty("Refunded", $this->http->FindSingleNode("$path//span[contains(text(), 'Refunded')]/following-sibling::span"));
        // Expired
        $this->SetProperty("Expired", $this->http->FindSingleNode("$path//span[contains(text(), 'Expired')]/following-sibling::span"));
    }

    protected function captchaWorkaround()
    {
        $this->logger->notice(__METHOD__);
        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $uuid = $this->http->FindPreg('/var uuid = "([\w\-]+)"/');
        $vid = $this->http->FindPreg('/var vid = "([\w\-]+)"/');
        $name = $this->http->FindPreg('/var name = "([\w\-]+)"/');
        $this->logger->debug("uuid: {$uuid}");
        $this->logger->debug("vid: {$vid}");
        $this->logger->debug("name: {$name}");

        $base64 = base64_encode(json_encode([
            'r' => $captcha, 'v' => '', 'u' => $uuid, ]
        ));
        $this->http->setCookie($name, "{$captcha}:{$uuid}:{$vid}", '', '/');
        $this->http->setCookie("_px2", $base64, '', '/');

        $this->http->RetryCount = 0;
        $current = $this->http->currentUrl();
//        $this->http->GetURL("https://www.kiva.org/px/captcha/?pxCaptcha={$base64}");
        $this->http->RetryCount = 2;
        $this->http->GetURL($current);

        return true;
    }

    protected function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);
        $enterprise = 0;

        if (isset($key)) {
            $enterprise = 1;
        } else {
            $key = $this->http->FindSingleNode("//div[@class = 'captcha-holder']/div[@class = 'g-recaptcha']/@data-sitekey");
        }

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => 'https://login.kiva.org/login',
            "proxy"      => $this->http->GetProxy(),
            'enterprise' => $enterprise,
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    protected function parseCaptchaImg($data)
    {
        $this->logger->notice(__METHOD__);
        $imageData = $this->http->FindPreg("/svg\+xml;base64\,\s*([^<]+)/ims", false, $data);
        $this->logger->debug("jpeg;base64: {$imageData}");

        if (!empty($imageData)) {
            $this->logger->debug("decode image data and save image in file");
            // decode image data and save image in file
            $imageData = base64_decode($imageData);

            if (!extension_loaded('imagick')) {
                $this->DebugInfo = "imagick not loaded";
                $this->logger->error("imagick not loaded");

                return false;
            }

            $im = new Imagick();
            $im->setBackgroundColor(new ImagickPixel('transparent')); //$im->setResolution(300, 300); // for 300 DPI example
            $im->readImageBlob($imageData);

            /*png settings*/
            $im->setImageFormat("png32");

            $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true) . ".jpeg";

            $im->writeImage($file);
            $im->clear();
            $im->destroy();
        }

        if (!isset($file)) {
            return false;
        }

        $this->logger->debug("file: " . $file);
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file, ["regsense" => 1]);
        unlink($file);

        return $captcha;
    }

    protected function captchaSeleniumWorkaround($selenium, $key)
    {
        $this->DebugInfo = 'reCAPTCHA checkbox';
        $captcha = $this->parseCaptcha($key->getAttribute('data-sitekey'));

        if ($captcha === false) {
            $this->logger->error("failed to recognize captcha");

            return false;
        }
        $selenium->driver->executeScript('window.handleCaptcha(' . json_encode($captcha) . ')');

        return true;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//header//p[contains(text(), "Check your preferred one-time password application for a code.")]');

        if (!isset($question) || !$this->http->ParseForm(null, '//header[//p[contains(text(), "Check your preferred one-time password application for a code.")]]/following-sibling::div/form')) {
            return false;
        }

        $this->http->FormURL = $this->http->currentUrl();

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_59);
            $selenium->setKeepProfile(true);

            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL('https://www.kiva.org/login?register=0&doneUrl=https%3A%2F%2Fwww.kiva.org%2Fportfolio');
            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 5);

            if (!$login && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 0))) {
                $this->savePageToLogs($selenium);
                $this->captchaSeleniumWorkaround($selenium, $key);
                $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 7);
            }// if (!$login && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 0)))

            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'sign-in-button']"), 0);

            if ($login && $pass && $btn) {
                $this->logger->debug("set credentials");
                $login->sendKeys($this->AccountFields['Login']);
                $pass->sendKeys($this->AccountFields['Pass']);
                $this->savePageToLogs($selenium);
                $btn->click();

                $success = $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Thank you, ")]'), 10);

                if ($success) {
                    $this->logger->info("Parse", ['Header' => 2]);
                    $selenium->http->GetURL("https://www.kiva.org/portfolio");
                    $selenium->Parse();

                    $this->SetBalance($selenium->Balance);
                    $this->Properties = $selenium->Properties;
                    $this->ErrorCode = $selenium->ErrorCode;

                    if ($this->ErrorCode != ACCOUNT_CHECKED) {
                        $this->ErrorMessage = $selenium->ErrorMessage;
                        $this->DebugInfo = $selenium->DebugInfo;
                    }
                }
            }

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            return $selenium->http->currentUrl();
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return null;
    }
}
