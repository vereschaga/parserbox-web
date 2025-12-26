<?php

// refs #15652
use AwardWallet\Engine\ProxyList;

class TAccountCheckerMetro extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], "metro")) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $result = $this->loginSuccessful();
        $this->http->RetryCount = 2;

        if ($result) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Email address must have the format name@domain.com.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.metro.ca/en/showLoginForm');

        // retries
        if ($this->http->Response['code'] == 0) {
            throw new CheckRetryNeededException(3, 10);
        }

        $csrf = $this->http->FindSingleNode("//form[@id = 'loginForm']//input[@name = '_csrf']/@value");

        if (!$this->http->ParseForm('loginForm') || !$csrf) {
            return $this->checkErrors();
        }
        $key = $this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
        $data = [
            'username'                     => $this->AccountFields['Login'],
            'password'                     => $this->AccountFields['Pass'],
            '_spring_security_remember_me' => 'true',
            '_csrf'                        => $csrf,
        ];
        // TODO: From the first time does not work!
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.metro.ca/login', $data);
        $this->challengeReCaptchaForm();

        $response = $this->http->JsonLog(null, 3, true);

        if (
            in_array(ArrayVal($response, 'errorMessage'), [
                "Please check the box below to confirm you're human.",
                "Veuillez cocher la case ci-dessous pour confirmer que vous êtes un humain.",
            ])
        ) {
            $captcha = $this->parseCaptcha($key);

            if ($captcha !== false) {
                $data['g-recaptcha-response'] = $captcha;
            }
            $this->http->PostURL('https://www.metro.ca/login', $data);
            $response = $this->http->JsonLog(null, 3, true);
            unset($data['g-recaptcha-response']);
        }

        if (ArrayVal($response, 'errorMessage') == 'Reload is needed: non instrusive conflict') {
            $this->http->PostURL('https://www.metro.ca/login', $data);
        }
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Website Update in Progress
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Website Update in Progress')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("
                //p[contains(text(), 'The web server reported a bad gateway error.')]
                | //h1[contains(text(), '502 Bad Gateway')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 3, true);
        $message = ArrayVal($response, 'errorMessage', null);

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                in_array($message, [
                    'Bad credentials',
                    'Les identifications sont erronées',
                ])
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException('Invalid email or password.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'You must read and accept terms and conditions.') {
                $this->captchaReporting($this->recognizer);
                $this->throwProfileUpdateMessageException();
            }

            if ($message == 'User is disabled (compromised password)') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException('We have recently been informed that your metro.ca account has been the subject of a suspicious connection in the past few months. According to our information, unauthorized access to your account has been made possible because your email address and password have been obtained beforehand from an external source which is in no way linked to Metro. In light of the above, and although this is not a security issue attributable to our systems, we have taken the initiative to block access to your account as a precaution.', ACCOUNT_LOCKOUT);
            }

            if ($message == 'Please check the box below to confirm you\'re human.') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException();
            }
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // provider bug fix - Balance 'Not available' workaround
        if ($this->http->FindSingleNode("//div[@class='jfmr--col-title' and contains(.,'points balance')]/following-sibling::div[@class='jfmr--big-text']") == 'Not available') {
            sleep(3);
            $this->http->GetURL("https://www.metro.ca/en/just-for-me/my-points");
            sleep(3);
            $this->http->GetURL("https://www.metro.ca/en/just-for-me/my-rewards");
        }

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[contains(@class, 'jfmr--title')]", null, false, '/Welcome\s*\,?\s*([^\!]+)/')));
        // My Metro points balance
        if (!$this->SetBalance($this->http->FindSingleNode(
            "//div[@class='jfmr--col-title' and contains(.,'points balance')]/following-sibling::div[@class='jfmr--big-text']", null, false, self::BALANCE_REGEXP))) {
            if ($this->http->FindSingleNode("//div[@class='jfmr--col-title' and contains(.,'points balance')]/following-sibling::div[@class='jfmr--big-text']") == 'Not available') {
                $this->SetWarning("Balance currently not available");
            }
        }
        // You've been a member since
        $this->http->GetURL('https://www.metro.ca/en/just-for-me/my-rewards/memberSinceBlock');
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//span", null, false, '#\d+/\d+/\d+#'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Date not available. If this is a new card, your account will be updated in a few days.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Date not available. If this is a new card, your account will be updated in')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $this->http->GetURL('https://www.metro.ca/en/just-for-me/my-rewards/block?tabletMobile=false');
        // Since joining the program, you've earned
        $this->SetProperty('TotalEarned', "$" . $this->http->FindPreg("/var totalAmount = \"([^\"]+)/"));
        // subAccounts - My Rewards -> This Year
        $prevYear = date('Y', strtotime('-1 year'));
        $rewards = $this->http->XPath->query(
            "//button[normalize-space(text())='This Year' or normalize-space(text())='{$prevYear}']
            /following-sibling::div[1]/table/tbody/tr[normalize-space(td[4])='' and normalize-space(td[5])='']");
        $this->logger->debug("Total {$rewards->length} rewards were found");

        foreach ($rewards as $reward) {
            // Code - CardNumber
            $rewardCheque = $this->http->FindSingleNode('td[1]', $reward, false, '/([\d*]+)/');
            // Points - balance
            $pointsBalance = $this->http->FindSingleNode('td[2]', $reward, false, self::BALANCE_REGEXP);
            // Expiry Date - ExpirationDate
            $expDate = strtotime($this->http->FindSingleNode('td[6]', $reward, false, '#\d+/\d+/\d+#'), false);

            if (isset($pointsBalance) && $expDate >= strtotime('now')) {
                $this->AddSubAccount([
                    'Code'           => 'metro' . str_replace('*', '', $rewardCheque),
                    'DisplayName'    => 'Reward Cheque №' . $rewardCheque,
                    'RewardCheque'   => $rewardCheque,
                    'Balance'        => $pointsBalance,
                    'ExpirationDate' => $expDate,
                ], true);
            }
        }// foreach ($rewards as $reward)

        if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) > 0) {
            $this->SetProperty("CombineSubAccounts", false);
        }
    }

    protected function parseCaptcha($key, $method = 'userrecaptcha')
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => $method,
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.metro.ca/en/just-for-me/my-rewards');
        // not a m member
        if ($this->http->FindSingleNode("(//span[normalize-space(text())='Add your metro&moi card to your metro.ca account to see your Rewards history.'])[1]")
            || $this->http->FindSingleNode("//span[contains(text(), 'Add your AIR MILES') and contains(., 'card to your metro.ca account to see your Rewards history.')]")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("(//a/span[text() = 'Sign out'])[1]")) {
            return true;
        }

        return false;
    }

    private function challengeReCaptchaForm()
    {
        $this->logger->notice(__METHOD__);
        $s = $this->http->FindSingleNode("//form[@id = 'challenge-form']//input[@name = 's']/@value");
        $id = $this->http->FindSingleNode("//form[@id = 'challenge-form']//script/@data-ray");
        /*
        if (!$id) {
            return false;
        }
        */
        $key = $this->http->FindSingleNode("//form[@id = 'challenge-form']//script/@data-sitekey");
        $method = 'userrecaptcha';

        if ($this->http->FindSingleNode("//form[@id = 'challenge-form']//input[@name = 'cf_captcha_kind' and @value = 'h']/@value")) {
            $method = "hcaptcha";
            $key = $key ?? "33f96e6a-38cd-421b-bb68-7806e1764460";
        }
        $captcha = $this->parseCaptcha($key, $method);

        if ($captcha == false) {
            return false;
        }

        if ($s && $id) {
            $s = urlencode($s);
            $this->http->GetURL("https://www.metro.ca/login?s={$s}&id={$id}&g-recaptcha-response={$captcha}#/index");
        } elseif ($method == "hcaptcha") {
//            $this->http->SetInputValue("id", $id);
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->SetInputValue("h-captcha-response", $captcha);
            $this->http->PostForm();
        } else {
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->PostForm();
        }

        return true;
    }
}
