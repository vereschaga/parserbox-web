<?php

class TAccountCheckerStandardchartered extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public $regionOptions = [
        ""          => "Select your region",
        //        "HongKong"  => "Hong Kong",
        "India"     => "India",
        //        "Indonesia" => "Indonesia",
        "Malaysia"  => "Malaysia",
        "Singapore" => "Singapore",
    ];

    private $customerGroup = ['Singapore'];
//    private $curlGroup = ['India', 'Malaysia'];
    private $curlGroup = [];
    /** @var HttpBrowser */
    private $browser = null;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        $arg["RedirectURL"] = $this->getLoginURL();
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'], $properties['Currency']) && strstr($properties['SubAccountCode'], "standardcharteredCashback")) {
            if ($properties['Currency'] == 'INR') {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "₹%0.2f " . $properties['Currency']);
            }
        }// if (isset($properties['Currency']))

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
        $this->KeepState = true;

        if (!in_array($this->AccountFields['Login2'], $this->curlGroup)) {
            $this->UseSelenium();
            $this->useGoogleChrome();
            /*
            $this->disableImages();
            */
            $this->http->saveScreenshots = true;
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->getLoginURL(), [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function getLoginURL()
    {
        switch ($this->AccountFields['Login2']) {
            case 'HongKong':
                $loginURL = 'https://www.sc.com/hk/promotions/credit-cards/new-reward-redemption/';

                break;

            case 'India':
                $loginURL = 'https://retail.sc.com/in/rewards360/rewards_home/in';

                break;

            case 'Malaysia':
                $loginURL = 'https://retail.sc.com/in/rewards360/rewards_home/my';

                break;

            case 'Singapore':
                $loginURL = 'https://retail.sc.com/sg/nfs/login.htm';

                break;

            case 'Indonesia':
                $loginURL = 'https://www.sc.com/nfs/orr/foa/login.htm?customerGroup=C&ctry=ID&lang=en_ID';

                break;

            default:
                $loginURL = 'https://www.sc.com/nfs/orr/foa/global.htm?timeout=1';

                break;
        }// switch ($this->AccountFields['Login2'])

        return $loginURL;
    }

    public function LoadLoginForm()
    {
        if (!in_array($this->AccountFields['Login2'], ['India', 'Singapore', 'HongKong', 'Malaysia'])) {
            $this->sendNotification("refs #16685. New account for {$this->AccountFields['Login2']} was found // RR");
        }

        if ($this->AccountFields['Login2'] == 'HongKong') {
            throw new CheckException("Unfortunately, Hong Kong region is not supported now.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->AccountFields['Login2'] == 'Indonesia') {
            throw new CheckException("As of 06 August 2022, the Online Reward Redemption (ORR) platform has been ceased permanently for customers in Indonesia.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->removeCookies();
        $loginURL = $this->getLoginURL();
        $this->http->GetURL($loginURL);

        if (in_array($this->AccountFields['Login2'], $this->customerGroup)) {
            if (!$this->http->ParseForm('loginForm')/* || !$modulus || !$exponent || !$rsaUniqueKey*/) {
                return $this->checkErrors();
            }

            $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'j_username']"), 10);

            if ($okBtn = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(),'Ok')]"), 0)) {
                $okBtn->click();
                $this->saveResponse();
            }

            $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'j_password']"), 0);
            $button = $this->waitForElement(WebDriverBy::xpath("//*[@name = 'Login']"), 0);
            $this->saveResponse();

            if ($message = $this->http->FindSingleNode('//div[contains(text(), "To better serve you, we will be carrying out maintenance")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (!$login || !$pass || !$button) {
                $this->logger->error("something went wrong");

                return $this->checkErrors();
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);

            try {
                $button->click();
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            }
        }// if (in_array($this->AccountFields['Login2'], ['Singapore', 'Indonesia']))
        elseif (in_array($this->AccountFields['Login2'], $this->curlGroup)) {// India
            if (!$this->http->ParseForm('sign_in_form') && !$this->http->ParseForm(null, "//div[@class = 'login-dialog']/form")) {
                return $this->checkErrors();
            }
            $this->http->FormURL = $loginURL . '/security/AuthenticateUserAsync';
            $this->http->SetInputValue('UserNameEncrypted', $this->AccountFields['Login']);
            $this->http->SetInputValue('UserNameMask', $this->AccountFields['Login']);
            $this->http->SetInputValue('PasswordEncrypted', $this->AccountFields['Pass']);
            $this->http->SetInputValue('PasswordEncryptionClientSide', "false");

            $this->http->unsetInputValue('UserNameEncryptionClientSide');
            $this->http->unsetInputValue('IsUserNameCaseSensitive');
        }// if (in_array($this->AccountFields['Login2'], $this->curlGroup))
        else {
            $loginLink = $this->waitForElement(WebDriverBy::xpath('//li[a[contains(@class, "login") and contains(text(), "Login")]]'), 10);

            if ($loginLink) {
//                $loginLink->click();
                $this->driver->executeScript('$(\'a.login:contains("Login")\').get(0).click();');
            }

            $login = $this->waitForElement(WebDriverBy::xpath("//input[@placeholder = 'Username']"), 10);
            $pass = $this->waitForElement(WebDriverBy::xpath("//input[@placeholder = 'Password']"), 0);

            if (!$login || !$pass) {
                $this->logger->error("something went wrong");
                $this->saveResponse();

                return $this->checkErrors();
            }
            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $button = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'login-button']"), 0);

            if (!$button) {
                $this->logger->error("something went wrong");
                $this->saveResponse();

                return $this->checkErrors();
            }

            $this->captchaWorkaround();

            $button->click();
        }

        return true;
    }

    private function captchaWorkaround(): bool
    {
        $this->logger->notice(__METHOD__);

        $captchaInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Enter Captcha text"]'), 0);

        if (
            $captchaInput
            && $captchaCanvas = $this->waitForElement(WebDriverBy::xpath('//canvas[starts-with(@id, "captchaCanvas")]'), 5)
        ) {
            $this->saveResponse();
            $captcha = $this->parseCaptchaImg($captchaCanvas);

            if (!$captcha) {
                return false;
            }

            $captchaInput->sendKeys($captcha);
            $this->saveResponse();

            return true;
        }

        return false;
    }

    protected function parseCaptchaImg($img)
    {
        $this->logger->notice(__METHOD__);

        if (!$img) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $pathToScreenshot = $this->takeScreenshotOfElement($img);
        $parameters = [
            'regsense'         => 1,
            'language'         => 2,
            'textinstructions' => 'Only lower register here / Здесь только маленькие буквы',
        ];
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot, $parameters);
        unlink($pathToScreenshot);

        return $captcha;
    }

    public function Login()
    {
        if (in_array($this->AccountFields['Login2'], $this->curlGroup)) {
            $headers = [
                "Accept"                     => "*/*",
                "X-Requested-With"           => "XMLHttpRequest",
                "__RequestVerificationToken" => $this->http->FindSingleNode("//input[@name = '__RequestVerificationToken']/@value"),
            ];

            if (!$this->http->PostForm($headers)) {
                return $this->checkErrors();
            }

            $response = $this->http->JsonLog();

            if ($response->SignInSuccessful ?? null) {
                return true;
            }

            $message = $response->Error ?? null;

            if (
                $message == 'Login Failed, Please call our Customer Service Hotline.'
                || $message == 'Invalid Username or Password, Please call our Customer Service Hotline.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        } else {
            $this->waitForElement(WebDriverBy::xpath('
                //div[contains(text(), "To activate SC Mobile Key, please key in the OTP sent to your mobile number")]
                | //div[contains(@class, "last-login-date")]/span
                | //img[@id = "user-profile-icon"]
                | //span[@class = "txt_error"]
            '), 20);
            $this->saveResponse();

            if ($question = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "To activate SC Mobile Key, please key in the OTP sent to your mobile number")]'), 0)) {
                $this->AskQuestion($question->getText(), null, "Question");

                return false;
            }
        }

        if ($this->loginSuccessful()) {
            return true;
        }
//        if ($this->parseQuestion())
//            return false;
        if (in_array($this->AccountFields['Login2'], $this->customerGroup)) {
            /*
             * Please ensure that your username/password is correct and the 'CAPS LOCK' key is turned off.
             * Call our Contact Center at 1800 747 7000 for further assistance.
             */
            if ($message = $this->http->FindSingleNode('//span[contains(text(), "Please ensure that your username/password is correct")]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message = $this->http->FindSingleNode('//span[@class = "txt_error"]')) {
                $this->logger->error("[Error]: {$message}");

                // Online Rewards is temporarily unavailable. Normal service will be restored soon. We apologize for any inconvenience caused.
                if (
                    strstr($message, 'Online Rewards is temporarily unavailable. ')
                    || strstr($message, 'As part of enhanced security measures to protect you from fraud and scams, you will only be able to login 12 hours after registration of your SC Mobile Key')
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    strstr($message, 'You have entered an invalid Username and/or Password. Please try again or click')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            }
        }// if (in_array($this->AccountFields['Login2'], ['Singapore', 'Indonesia']))
        else {
            // Invalid Username or Password. Please try again.
            if ($message = $this->http->FindSingleNode('//li[contains(text(), "Invalid Username or Password. Please try again.")]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            /*
             * The login service of 360° Rewards Redemption Platform at www.sc.com/hk/rewards is unavailable now due to system maintenance.
             * Please login via Standard Chartered Online Banking to redeem rewards.
             * The service will be resumed as soon as possible. Sorry for the inconvenience caused.
             */
            if ($message = $this->http->FindSingleNode('//div[contains(text(), "The login service of 360° Rewards Redemption Platform at ") and contains(text(), "system maintenance")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Invalid Username or Password, Please call our Customer Service Hotline.
            if ($message = $this->http->FindSingleNode('//div[contains(text(), "Invalid Username or Password, Please call our Customer Service Hotline.")]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message = $this->http->FindSingleNode('//label[contains(@class, "error-label")]')) {
                $this->logger->error("[Error]: {$message}");

                // Sorry for the inconvienince, due to some technical issue, not able to login. Please login after sometime.
                if (
                    strstr($message, 'Sorry for the inconvienince, due to some technical issue, not able to login. Please login after sometime.')
                    || $message == 'Unable to proceed due to technical reason. Please try again later'
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    strstr($message, 'Invalid Username or Password, Please call our Customer Service Hotline.')
                    || strstr($message, 'Invalid card details. Login failed')
                    || strstr($message, 'Invalid Username or Password. Please try again.')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                return false;
            }
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->sendNotification("code was entered // RR");
        $otp = $this->waitForElement(WebDriverBy::xpath('//input[@name="securityToken"]'), 5);
        $submit = $this->waitForElement(WebDriverBy::xpath('//input[@value="Submit"]'), 0);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->saveResponse();

        if (!$otp || !$submit) {
            return false;
        }

        $otp->sendKeys($answer);
        $submit->click();

        $this->waitForElement(WebDriverBy::xpath('//error'), 5); // todo
        $this->saveResponse();
//        $error = $this->http->FindSingleNode('//error');
//
//        if ($error) {
//            $this->AskQuestion($this->Question, $error);
//
//            return false;
//        }// if ($error)

        return true;
    }

    public function Parse()
    {
        if (in_array($this->AccountFields['Login2'], $this->customerGroup)) {
            // Name
            $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[contains(@class, "last-login-date")]/span')));

            $rewardsLink = $this->waitForElement(WebDriverBy::xpath('//a[@href="/sg/nfs/ibank/online_rewards_entry.htm"]'), 10);
            $this->saveResponse();

            if (!$rewardsLink) {
                if ($message = $this->http->FindSingleNode('//div[@class="sc-empty__message" and contains(., "We are currently unable to retrieve your account information. ")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return;
            }

            $rewardsLink->click();

            $this->waitFor(function () {
                return !$this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Authenticate your Login')]"), 0);
            });

            $this->saveResponse();
            $this->waitForElement(WebDriverBy::xpath('//input[contains(@value, "Rewards Homepage")] | //div[contains(text(), "Authenticate your Login")]'), 10);
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Authenticate your Login')]"), 0)) {
                throw new CheckException("To complete the sign-in, you should respond to the notification that was sent to you", ACCOUNT_PROVIDER_ERROR);
            }

            $rewardsHomepageLink = $this->waitForElement(WebDriverBy::xpath('//input[contains(@value, "Rewards Homepage")]'), 0);

            if (!$rewardsHomepageLink) {
                return;
            }

            $rewardsHomepageLink->click();

            // Balance - Pts
            $balance = $this->waitForElement(WebDriverBy::xpath('//label[contains(@class, "point-balances")]'), 10);
            $this->saveResponse();

            if ($balance) {
                $this->SetBalance($this->http->FindPreg("/(.+)\s+Pts/ims", false, $balance->getText()));
            }
            /*
            // Balance - Points
            $this->SetBalance($this->http->FindSingleNode('//font[contains(., "You have")]/a/span'));
            // Points Expiry
            $this->SetProperty("CombineSubAccounts", false);
            $cards = $this->http->XPath->query('//tr[th[contains(text(), "Participating Card No.")]]/following-sibling::tr[@class and not(contains(@class, "total"))]');
            $this->logger->debug("Total {$cards->length} cards were found");

            foreach ($cards as $card) {
                $displayName = $this->http->FindSingleNode('td[2]', $card);
                $code = $this->http->FindSingleNode('td[2]', $card, true, "/(\d+)\s*$/");
                $balance = $this->http->FindSingleNode('td[3]', $card);

                if ($displayName && $balance != '-') {
                    $subAcc = [
                        "Code"              => 'standardchartered' . $this->AccountFields['Login2'] . $code,
                        'DisplayName'       => "Card #{$displayName}",
                        'Balance'           => $balance,
                        "Number"            => $code,
                        "BalanceInTotalSum" => true,
                    ];

                    for ($i = 4; $i < 7; $i++) {
                        $expBalance = $this->http->FindSingleNode("td[{$i}]", $card);
                        $expDate = $this->http->FindSingleNode('//tr[th[contains(text(), "Participating Card No.")]]/following-sibling::tr[not(@class)]/th[' . ($i - 3) . ']', $card);

                        if ($expBalance != '-') {
                            $subAcc['ExpiringBalance'] = $expBalance;
                            $subAcc['ExpirationDate'] = strtotime('last day of ' . $expDate, false);

                            break;
                        }// if ($expBalance != '-')
                    }// for ($i = 4; $i < 8; $i++)
                    $this->AddSubAccount($subAcc, true);
                }// if ($displayName && $balance != '-')
            }// foreach ($cards as $card)

            try {
                $this->http->GetURL("https://www.sc.com/nfs/orr/foa/shopping_cart_entry.htm");
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }

            if (
                !$this->waitForElement(WebDriverBy::xpath("//td[contains(text(), 'You have not selected any products for redemption.')]"), 10)
                && !$this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Your session has timed out for security reasons.')]"), 0)
            ) {
                $this->sendNotification("refs #16685 - Products for redemption were found // RR");
            }
            $this->saveResponse();
            */
        }// if (in_array($this->AccountFields['Login2'], $this->customerGroup))
        else {
            $this->waitForElement(WebDriverBy::xpath('//button[@id = "button-container" and //span[contains(@class, "last-login")]]'), 0);
            $this->saveResponse();
            $this->driver->executeScript('$(\'#button-container.login\').click()');
            $account = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Account Summary")]'), 5);
            $this->saveResponse();
            $this->driver->executeScript('$(\'a:contains("Account Summary")\').get(0).click()');

            $details = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Points Summary")]/ancestor::div[@class = "a-card"]//button[contains(text(), "more details")]'), 10);
            $this->saveResponse();

            if (!$details) {
                return;
            }
//            $details->click();
            $this->driver->executeScript('$(\'div:contains("Points Summary")\').parent(\'div.a-card\').find(\'button:contains("more details")\').get(0).click();');
            $this->waitForElement(WebDriverBy::xpath('//td[contains(text(), "Total Points Available")]'), 10);
            $this->saveResponse();

            // Name
            $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//span[contains(text(), "Welcome,")]/text()[1]', null, true, "/Welcome,\s*([^!]+)/")));
            // Balance - Points
            if ($this->http->FindSingleNode('//th[contains(text(), "Participating Card Number")]/following-sibling::th[1]', null, true, "/Card Name/")) {
                $this->SetBalance($this->http->FindSingleNode('//td[contains(text(), "Total Points Available")]/following-sibling::td[2]', null, true, "/([^<]+)\s+Pts/"));
            } else {
                $this->SetBalance($this->http->FindSingleNode('//td[contains(text(), "Total Points Available")]/following-sibling::td[1]', null, true, "/([^<]+)\s+Pts/"));
            }
            // Expiry Details
            $expData = $this->findExpDate();

            if ($expData != null) {
                $this->SetProperty('ExpiringBalance', $expData['ExpiringBalance']);
                $this->SetExpirationDate($expData['ExpirationDate']);
            }

            // Cashback
            $cashback = $this->http->FindSingleNode('//li[@id = "cashback-active"]/label', null, true, "/[^\-\d]+(.+)/");

            if (isset($cashback) && ($back = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "BACK")]'), 0))) {
//                $back->click();
                $this->driver->executeScript('$(\'span:contains("BACK")\').click()');
                $this->saveResponse();
                $details = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Cashback Summary")]/ancestor::div[@class = "a-card"]//button[contains(text(), "more details")]'), 10);
                $this->saveResponse();

                if (!$details) {
                    return;
                }
//                $details->click();
                $this->driver->executeScript('$(\'div:contains("Cashback Summary")\').parent(\'div.a-card\').find(\'button:contains("more details")\').get(0).click();');
                $this->waitForElement(WebDriverBy::xpath('//td[contains(text(), "Total CashBack Available")]'), 10);
                $this->saveResponse();

                $subAcc = [
                    'Code'        => "standardchartered{$this->AccountFields['Login2']}Cashback",
                    'DisplayName' => 'Cashback',
                    'Balance'     => $cashback,
                    'Currency'    => $this->http->FindSingleNode('//li[@id = "cashback-active"]/label', null, true, "/([^\-\d]+)/"),
                ];
                $expData = $this->findExpDate('Cashback');

                if ($expData != null) {
                    $subAcc['ExpirationDate'] = $expData['ExpirationDate'];
                    $subAcc['ExpiringBalance'] = $expData['ExpiringBalance'];
                }
                $this->AddSubAccount($subAcc, true);
            }
        }
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());
    }

    public function getLocalStorageItem($item)
    {
        return preg_replace(['/^"/', '/"$/'], "", stripcslashes($this->driver->executeScript("return localStorage.getItem('{$item}');")));
    }

    public function findExpDate($header = 'Points')
    {
        $result = null;
        $rewardProductChannelType = "1";

        if ($header == 'Cashback') {
            $rewardProductChannelType = "2";
        }

        $this->parseWithCurl();

        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/json",
            "cid"             => $this->getLocalStorageItem('selected-country'),
            "Authorization"   => $this->driver->executeScript("return localStorage.getItem('token');"),
            "moduleInfo"      => "CP",
            "deviceInfo"      => "web",
            "Origin"          => "https://retail.sc.com",
            "Referer"         => "https://retail.sc.com/in/rewards360/rewards_home/my/myaccount/0",
        ];
        $data = [
            "customerGuid"             => $this->getLocalStorageItem('guid'),
            "countryCode"              => $this->getLocalStorageItem('selected-country'),
            "languageId"               => $this->getLocalStorageItem('selected-language'),
            "rewardProductChannelType" => $rewardProductChannelType,
            "cardType"                 => null,
            "rewardAccountCardType"    => null,
            "tempToken"                => null,
            "operationType"            => "13",
            "sourceEvent"              => "web",
        ];
        $this->browser->RetryCount = 0;
        $this->browser->PostURL("https://retail.sc.com/in/rewards360/scb-customerPortalBackend/scb/rre/get-rewardAccount", json_encode($data), $headers);
        $data = $this->browser->JsonLog(null, 3, false, 'pointsExpiryDetails');

        if (!isset($data->values)) {
            return null;
        }

        foreach ($data->values as $value) {
            foreach ($value->pointsExpiryDetails as $pointsExpiryDetail) {
                $expiryDate = intval(preg_replace('/000$/', '', $pointsExpiryDetail->expiryDate));

                if (
                    (!isset($exp) || $expiryDate <= $exp)
                    && $pointsExpiryDetail->pointsExpiring > 0
                    && $expiryDate != 253402214400
                ) {
                    $exp = $expiryDate;
                    $result['ExpirationDate'] = $exp;
                    $result['ExpiringBalance'] = number_format($pointsExpiryDetail->pointsExpiring);
                }
            }
        }

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//div[contains(@class, "last-login-date")]/span')) {// Singapore
            return true;
        }
//        if ($this->http->FindSingleNode('//a[contains(@href, "SignOut")]'))// India
        if ($this->http->FindSingleNode('//a[contains(text(), "Logout")]')) {// India
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Online 360° Rewards is undergoing maintenance to serve you better
        if ($message = $this->http->FindSingleNode("
                //*[self::div or self::h2][contains(text(), 'Online 360° Rewards is undergoing maintenance to serve you better')]
                | //h6[contains(text(), 'Sorry, the page you are looking for is unavailable.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
