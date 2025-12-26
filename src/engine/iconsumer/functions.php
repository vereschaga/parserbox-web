<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerIconsumer extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.iconsumer.com/html/dashboard.cfm';

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
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
        $this->http->removeCookies();
        $this->http->GetURL('https://www.iconsumer.com/');

        if (!$this->http->ParseForm('frmLogin')) {
            return $this->checkErrors();
        }
        $logGuid = $this->http->FindSingleNode('//input[@id="LoginGUID"]/@value');
        $logHash = $this->http->FindSingleNode('//input[@id="loginHASH"]/@value');

        if (!$logGuid | !$logHash) {
            return false;
        }

        $keyCaptcha = $this->parseCaptcha();

        if ($keyCaptcha === false) {
            return false;
        }

        $this->http->Form = [];
        $this->http->SetInputValue('LoginGUID', $logGuid);
        $this->http->SetInputValue('loginHASH', $logHash);
        $this->http->SetInputValue('loginemail', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('g-recaptcha-response', $keyCaptcha);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "You have entered an incorrect combination of email address and password")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode('//h3[contains(text(), "Our records indicate that your account has been deactivated.")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Our records indicate that your account has been deactivated.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Balance - Cash Total Earned
        $this->SetBalance($this->http->FindSingleNode('//div[p[contains(text(), "Cash Total Earned")]]//strong', null, true, '/\\$(.+)/'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//span[@id="HeaderUsersName"]')));
        // Earned Shares
        $this->SetProperty('EarnedShares', $this->http->FindSingleNode('//div[h5[contains(text(), "Stock Status")]]//div[h6[contains(text(), "Earned")]]/following-sibling::div/h2'));
        // Pending Shares
        $this->SetProperty('PendingShares', $this->http->FindSingleNode('//div[h5[contains(text(), "Stock Status")]]//div[h6[starts-with(normalize-space(text()), "Pending")]]/following-sibling::div/h2'));
        // Current Market Value
        $this->SetProperty('CurrentMarketValue', $this->http->FindSingleNode('//h3[strong[contains(text(), "Current Market Value")]]', null, true, '/(.+)\s+Current Market/'));

        // Pending
        $pending = $this->http->FindSingleNode('//div[h5[contains(text(), "Cash Status")]]//div[h6[contains(text(), "Pending")]]/following-sibling::div/h2', null, true, '/\\$(.+)/');
        // Earned
        $earned = $this->http->FindSingleNode('//div[h5[contains(text(), "Cash Status")]]//div[h6[contains(text(), "Earned")]]/following-sibling::div/h2', null, true, '/\\$(.+)/');
        // Paid
        $paid = $this->http->FindSingleNode('//div[h5[contains(text(), "Cash Status")]]//div[h6[contains(text(), "Paid")]]/following-sibling::div/h2', null, true, '/\\$(.+)/');

        if (!$pending || !$earned || !$paid) {
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if (!empty($this->Properties['Name'])
                    && !empty($this->Properties['EarnedShares'])
                    && !empty($this->Properties['PendingShares'])
                    /*
                    && in_array($this->AccountFields['Login'], [
                    'veresch80@yahoo.com',
                    'marshdw@gmail.com',
                    'Toddmaddox@gmail.com', // AccountID: 5426119
                    'liuyuhao@umich.edu', // AccountID: 5434628
                    'potollo.muck@gmail.com', // AccountID: 5565404
                    'holliemae@gmail.com', // AccountID: 5528291
                    'johnstupak@gmail.com', // AccountID: 5787210
                    'codsonpark@gmail.com', // AccountID: 5821847
                    'scott2828@gmail.com', // AccountID: 5967971
                    'purrpplee@gmail.com', // AccountID: 6012518
                ])
                    */
                ) {
                    if (!$this->http->FindSingleNode('//text()[contains(text(), "Cash Total")]')) {
                        $this->SetBalanceNA();
                    }
                }
            }

            return;
        }

        // Pending
        $this->AddSubAccount([
            "Code"              => "iconsumerPending",
            "DisplayName"       => "Pending",
            "Balance"           => $pending,
            "BalanceInTotalSum" => true,
        ]);

        // Earned
        $this->AddSubAccount([
            "Code"              => "iconsumerEarned",
            "DisplayName"       => "Earned",
            "Balance"           => $earned,
            "BalanceInTotalSum" => true,
        ]);

        // Paid
        $this->AddSubAccount([
            "Code"        => "iconsumerPaid",
            "DisplayName" => "Paid",
            "Balance"     => $paid,
        ]);
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        // find data-sitekey in form
        $key = $this->http->FindSingleNode('(//form[@id="frmLogin"]//div[@class="g-recaptcha"]/@data-sitekey)');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(text(), "Log Out")]/@href')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
