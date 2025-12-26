<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerKrispy extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    /**
     * @var CaptchaRecognizer
     */
    public $recognizer;

    public $regionOptions = [
        ""    => "Please select your country",
        "US"  => "USA",
        "UK"  => "UK",
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //$arg['CookieURL'] = 'http://www.krispykreme.co.uk/';

        return $arg;
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login2'] == 'US') {
            $arg["RedirectURL"] = 'https://www.krispykreme.com/account/profile';
        }
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            switch ($properties['Currency']) {
                case '$':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");

                default:
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f");
            }
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->AccountFields['Login2'] == 'UK') {
            $this->http->SetProxy($this->proxyUK());
        }
    }

    public function IsLoggedIn()
    {
        switch ($this->AccountFields['Login2']) {
            case 'US':
//                $this->http->RetryCount = 0;
//                $this->http->GetURL('https://www.krispykreme.com/account/profile', [], 20);
//                $this->http->RetryCount = 2;
//                if ($this->loginSuccessful()) {
//                    return true;
//                }
                break;

            default:
                $this->http->RetryCount = 0;
                $this->http->GetURL('https://www.krispykreme.co.uk/customer/account/index/', [], 20);
                $this->http->RetryCount = 2;

                if ($this->loginSuccessful()) {
                    return true;
                }

                break;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        switch ($this->AccountFields['Login2']) {
            case 'US':
                $this->selenium();

                return false;
                $this->http->GetURL('https://www.krispykreme.com/account/sign-in');

                if (!$this->http->ParseForm('aspnetForm')) {
                    return $this->checkErrors();
                }
                $this->http->SetInputValue('ctl00$plcMain$txtUserName', $this->AccountFields['Login']);
                $this->http->SetInputValue('ctl00$plcMain$txtPassword', $this->AccountFields['Pass']);
                $this->http->SetInputValue('ctl00$plcMain$btnSubmit', 'Sign In');

                return !$this->Redirecting;

                break;

            default:
//                $this->http->GetURL('https://www.krispykreme.co.uk/customer/account/login/');
                $this->http->GetURL('https://www.krispykreme.co.uk/rewards');

                if ($this->http->ParseForm("frmCaptcha")) {
                    $captcha = $this->parseCaptcha();

                    if ($captcha === false) {
                        return false;
                    }

                    $this->http->FormURL = 'https://www.krispykreme.co.uk/AtaVerifyCaptcha';
                    $this->http->SetInputValue('g-recaptcha-response', $captcha);
                    $this->http->PostForm();
                }

                $formKey = $this->http->FindSingleNode('//form[@id = "form-giftcard-balance-check"]//input[@name = "form_key"]/@value');

                if (!$formKey) {
                    return false;
                }
                $this->http->setCookie('form_key', $formKey);
                $headers = [
                    "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
                    "Accept-Encoding" => "gzip, deflate, br",
                    "Content-Type"    => "application/x-www-form-urlencoded",
                    "Referer"         => "https://www.krispykreme.co.uk/customer/account/login/",
                ];
                $data = [
                    'form_key'        => $formKey,
                    'login[username]' => $this->AccountFields['Login'],
                    'login[password]' => $this->AccountFields['Pass'],
                    'send'            => "",
                ];
                $this->http->PostURL("https://www.krispykreme.co.uk/customer/account/loginPost/", $data, $headers);

                $mageMessages = $this->http->getCookieByName('mage-messages');
                $incorrectLoginRe = '/The\saccount\ssign-in\swas\sincorrect\sor\syour\saccount\sis\sdisabled\stemporarily\.\sPlease\swait\sand\stry\sagain\slater\./';

                if ($this->http->FindPreg($incorrectLoginRe, false, urldecode($mageMessages))) {
                    $message = 'The account sign-in was incorrect or your account is disabled temporarily. Please wait and try again later.';

                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($this->http->currentUrl() == 'https://www.krispykreme.co.uk/terms-conditions') {
                    $this->throwAcceptTermsMessageException();
                }

                break;
        }

        return true;
    }

    public function selenium()
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
            $selenium->useCache();
            $selenium->disableImages();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.krispykreme.com/account/sign-in?returnUrl=%2faccount%2fprofile");
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "ctl00$plcMain$txtUserName"]'), 5);
            $passInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "ctl00$plcMain$txtPassword"]'), 0);
            $submit = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "ctl00$plcMain$btnSubmit"]'), 0);
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if (!$loginInput || !$passInput || !$submit) {
                return $result;
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passInput->sendKeys($this->AccountFields['Pass']);
            $submit->click();

            $res = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "st Name")]/following-sibling::div'), 10);
            // save page to logs
            $selenium->saveResponse();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if ($res) {
                $this->ParseUS($selenium);

                return;
            }

            // US
            if ($message = $this->http->FindSingleNode("//div[
                    contains(text(), 'Invalid credentials.')
                    or contains(text(), 'The field Email Address is invalid.')
                    or contains(text(), 't recognize that account.')
                ]")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message = $this->http->FindSingleNode("//div[
                    contains(text(), 'Unable to sign you in.')
                ]")
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindSingleNode("//h1[contains(text(), 'CONFIRM YOUR ACCOUN')]")) {
                $this->throwProfileUpdateMessageException();
            }

            // TERMS OF USE AGREEMENT
            if (
                $this->http->FindSingleNode('//input[@value="Accept Terms"]/@value')
                && strstr($selenium->http->currentUrl(), '/account/terms?returnUrl=%2faccount%2fprofile')
            ) {
                $this->throwAcceptTermsMessageException();
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $result = true;
        } catch (NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $retry = true;
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
                throw new CheckRetryNeededException(3);
            }
        }

        return $result;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        // UK
        if ($message = $this->http->FindPreg('/(The Email or Card Number and Password you provided do not match\. Please verify and try again\.)/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // US
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Invalid credentials.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // TERMS OF USE AGREEMENT
        if (
            ($this->http->FindSingleNode('//input[@value="Accept Terms"]/@value') && strstr($this->http->currentUrl(), '/account/terms?returnUrl=%2faccount%2fprofile'))
            || (strstr($this->http->currentUrl(), '/rewards-terms') && $this->http->FindSingleNode('//p[contains(text(), "Please read these Terms and Conditions before agreeing to join the Krispy")]'))
        ) {
            $this->throwAcceptTermsMessageException();
        }

        return $this->checkErrors();
    }

    public function ParseUS($browser = null)
    {
        $this->logger->notice(__METHOD__);
        // for FormatBalance
        $this->SetProperty('Currency', "$");

        if ($browser) {
            // save page to logs
            $this->http->SetBody($browser->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        }

        // Name
        $this->SetProperty('Name', beautifulName(join(' ', $this->http->FindNodes("//span[contains(text(),'st Name')]/following-sibling::div/div[1]/p"))));

        if ($browser) {
            $browser->http->GetURL('https://www.krispykreme.com/account/my-card');
//            $browser->waitForElement(WebDriverBy::xpath("//span[contains(text(),'Card Balance')]/preceding-sibling::strong[normalize-space(text()) != '']"), 10);
            // save page to logs
            $this->http->SetBody($browser->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } else {
            $this->http->GetURL('https://www.krispykreme.com/account/my-card');
        }
        // Card Number
        $this->SetProperty('Number', $this->http->FindSingleNode("//img[@id='ctl00_plcMain_imgBarcode']/@alt", null, false, '/Card #(\w+)/'));
        // Card Balance
        if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(text(),'Card Balance')]/preceding-sibling::strong"))) {
            if ($this->http->FindPreg("/(500: Error: Unable to retrieve balance.|504: Endpoint request timed out)/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        if ($browser) {
            $browser->http->GetURL('https://www.krispykreme.com/account/rewards');
            // save page to logs
            $this->http->SetBody($browser->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } else {
            $this->http->GetURL('https://www.krispykreme.com/account/rewards');
        }
        $rewards = $this->http->XPath->query("//div[@class='reward-details']//ul/li");
        $this->logger->debug("Total {$rewards->length} rewards were found");

        foreach ($rewards as $reward) {
            $displaySubtitle = $this->http->FindSingleNode(".//div[@class='reward-copy']/div[@class='subtitle']", $reward);
            $displayLabel = $this->http->FindSingleNode(".//span[@class='reward-graphic-label']/span", $reward);
            $this->SetProperty("Credits" . str_replace(' ', '', $displayLabel), $this->http->FindPreg('/^([\d.,]+)\s+more/', false, $displaySubtitle));
        }

        $rewards = $this->http->FindNodes("//div[@class='reward-details']//ul/li//span[@class='reward-graphic-label']/text()", null, '#\d+/\d+#');
        $this->logger->debug(var_export($rewards, true));

        if (isset($rewards) && array_search('12/12', $rewards) !== false || count($rewards) != 4) {
            $this->sendNotification('refs#18007 - check values typed //MI');
        }
    }

    public function Parse()
    {
        if ($this->AccountFields['Login2'] == 'US') {
            $this->ParseUS();

            return;
        }

        $this->ParseUK();

        return;

        $this->http->GetURL('https://friends.krispykremerewards.co.uk/Friends/MyInfo/MyInfo.aspx');
        $firstName = trim($this->http->FindSingleNode('//input[@id=\'ContentPlaceHolder1_wucSignInStep1_txtFName\']/@value'));
        $lastName = trim($this->http->FindSingleNode('//input[@id=\'ContentPlaceHolder1_wucSignInStep1_txtLName\']/@value'));
        // Name
        $this->SetProperty('Name', trim(beautifulName($firstName . ' ' . $lastName)));
        // Rewards
        $this->http->GetURL('https://friends.krispykremerewards.co.uk/Friends/MyRewards/CourtesiesView.aspx');
        $rewards = $this->http->XPath->query('//div[@id= "ContentPlaceHolder1_CourtesiesView1_divMyRewards"]//table/tbody/tr');
        $this->logger->debug("Total {$rewards->length} rewards were found");

        foreach ($rewards as $reward) {
            $displayName = $this->http->FindSingleNode('td[1]/span[1]', $reward);
            $readyToRedeem = trim($this->http->FindSingleNode('td[2]/span[1]', $reward));

            if ($readyToRedeem != 'Yes') {
                $this->logger->notice("skip non redeemable rewards -> '$displayName'");

                continue;
            }
            // Expiration Date
            $date = date_create_from_format('M-j-Y', $this->http->FindSingleNode('td[3]/span[1]', $reward));

            if (!$date || !$displayName) {
                $this->logger->error("something went wrong");

                continue;
            }
            $exp = $date->getTimestamp();
            $this->AddSubAccount([
                'Code'           => 'krispy' . md5($displayName) . $exp,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => $exp,
            ]);
        }// foreach ($rewards as $reward)

        // set BalanceNA
        if (!empty($this->Properties['Name']) && !empty($this->Properties['SubAccounts'])) {
            $this->SetBalanceNA();
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case 'US':
                if ($this->http->FindSingleNode("//h1[contains(text(), 'Profile & Settings')]")) {
                    return true;
                }

                break;

            default:
                if ($this->http->FindSingleNode('//a[contains(@href, "logout")]/@href')) {
                    return true;
                }

                break;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Server Error in '/' Application.
        if ($this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function ParseUK()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.krispykreme.co.uk/loyalty/account/');
        // Membership Number
        $this->SetProperty('Number', $this->http->FindSingleNode('//h4[contains(normalize-space(),"Membership Number")]/following-sibling::span[1]'));
        // Smiles Balance
        $this->SetBalance($this->http->FindSingleNode('//h4[contains(normalize-space(),"Smiles Balance")]/following-sibling::span[1]', null, true, "/([\d\.\,\-\s]+?)\sSmiles/"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                $this->http->FindPreg('/account\/join/', false, $this->http->currentUrl())
                && $this->http->FindSingleNode('//p[contains(text(), "Join Krispy Kreme Rewards")]')
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->SetWarning($this->http->FindSingleNode('//p[contains(text(), "We have been unable to load your loyalty account at this time or your account is no longer active.")]'));
        }

        $this->http->GetURL('https://www.krispykreme.co.uk/customer/account/edit/');
        $firstName = $this->http->FindSingleNode('//input[@id = "firstname"]/@value');
        $lastName = $this->http->FindSingleNode('//input[@id = "lastname"]/@value');
        // Name
        $this->SetProperty('Name', trim(beautifulName("{$firstName} {$lastName}")));

        // Wallet
        $this->http->GetURL('https://www.krispykreme.co.uk/loyalty/account/wallet/');
        $items = $this->http->XPath->query("//ul[contains(@class, 'loyalty-wallet')]/li");
        $this->logger->debug("Total {$items->length} rewards were found");

        foreach ($items as $item) {
            $displayName = $this->http->FindSingleNode('.//h4[contains(@class, "loyalty-wallet-item__title")]', $item);
            $balance = $this->http->FindSingleNode('.//p[contains(@class, "loyalty-wallet-item__smiles")]', $item, true, "/\-?(\d+)/");
            $exp = strtotime($this->ModifyDateFormat($this->http->FindSingleNode(".//p[contains(text(), 'Valid to:')]", $item, false, '/:\s+(.+)/')));

            $this->AddSubAccount([
                'Code'           => 'reward' . $exp,
                'DisplayName'    => $displayName,
                'Balance'        => $balance,
                'ExpirationDate' => $exp,
            ], true);
        }
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'frmCaptcha']//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
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
}
