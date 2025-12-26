<?php

class TAccountCheckerListia extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.listia.com/account/credit_activity';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
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
        $this->http->GetURL('https://www.listia.com/login');

        if (!$this->http->ParseForm(null, "//form[@action='/login']")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('login', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember_me', '1');
        /*
        unset($this->http->Form['remember_me']);
        */
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function checkErrors()
    {
        // Listia is experiencing some technical issues. We are working hard to fix this asap and Listia should be back up shortly.
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Listia is experiencing some technical issues.')]")) {
            throw new CheckException($message . " We are working hard to fix this asap and Listia should be back up shortly.", ACCOUNT_INVALID_PASSWORD);
        }
        // We're sorry, too many people are accessing this website at the same time. We're working on this problem. Please try again later.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, too many people are accessing this website at the same time.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode("//div[@class='flash_alert']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.listia.com/rewards/transactions", [], 20);
        // AddSubAccount
        $pending = $this->http->FindSingleNode('//td[@class = "credits pending"]', null, true, "/([\d\.\,\-]+)\sPTS/ims");
        $available = $this->http->FindSingleNode('//td[@class = "credits available" and @id="credits-available-cell"]', null, true, "/([\d\.\,\-]+)\sPTS/ims");

        if (isset($pending)) {
            $subAccount = [
                'Code'              => 'listiaPendingPTS',
                'DisplayName'       => 'Pending',
                'Balance'           => $pending,
                "BalanceInTotalSum" => true,
            ];
            $this->AddSubAccount($subAccount, true);
        }

        if (isset($available)) {
            $subAccount = [
                'Code'              => 'listiaAvailablePTS',
                'DisplayName'       => 'Available',
                'Balance'           => $available,
                "BalanceInTotalSum" => true,
            ];
            $this->AddSubAccount($subAccount, true);
        }
        // 0,000.0 PTS to go!
        $this->SetProperty("PointsToReward", $this->http->FindSingleNode('//div[@class = "redeem-status"]/div[@class = "action"]/p[@class = "points-needed"]', null, true, self::BALANCE_REGEXP));
        // Balance - Total	0.00 PTS
        $this->SetBalance($this->http->FindSingleNode('//div[@class = "profile"]/div[@class = "points"]', null, true, self::BALANCE_REGEXP));
        // Credits Available 0 Props
        $this->http->GetURL("https://www.listia.com/account/credit_activity", [], 20);
        $this->SetProperty("CreditAvailable", $this->http->FindSingleNode("//th[normalize-space() = 'Credits Available']/following-sibling::td", null, true, self::BALANCE_REGEXP));
        // profile link
        if ($link = $this->http->FindSingleNode("//*[@id='left']//div[@class='account_login']//a[contains(@class, 'colored')]/@href")) {
            $this->http->GetURL('https://www.listia.com' . $link);
        }
        // set Level/Experience
        $this->SetProperty('LevelExperience', $this->http->FindSingleNode("//div[contains(text(), 'Level / Experience')]/following-sibling::div[1]"));
        // set Check Ins
        $this->SetProperty('CheckIns', $this->http->FindSingleNode("//div[contains(text(), 'Check Ins')]/following-sibling::div[1]"));
        // set Seller Feedback Received
        $this->SetProperty('SellerFeedbackReceived', $this->http->FindSingleNode("//div[contains(text(), 'Seller Feedback Received')]/following-sibling::div[1]"));
        // Member Since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//span[contains(text(), 'Member since')]/b[1]"));

        $this->http->GetURL("https://www.listia.com/account/edit");
        // set Seller Feedback Received
        $this->SetProperty('Name', beautifulName(trim($this->http->FindSingleNode("//input[@id='user_first_name']/@value") . ' ' . $this->http->FindSingleNode("//input[@id='user_last_name']/@value"))));

        // Total 0 Props
        $this->http->GetURL("https://www.listia.com/account/props_activity", [], 20);
        $this->SetProperty("Props", $this->http->FindSingleNode("//th[normalize-space() = 'Total']/following-sibling::td", null, true, self::BALANCE_REGEXP));

        // Available 0 XNK
        $this->http->GetURL("https://www.listia.com/account/xnk_activity", [], 20);
        $this->SetProperty("XNK", $this->http->FindSingleNode("//th[normalize-space() = 'Available']/following-sibling::td", null, true, self::BALANCE_REGEXP));
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@action = '/login']//button[contains(@class, 'g-recaptcha')]/@data-sitekey");
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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")) {
            return true;
        }

        return false;
    }
}
