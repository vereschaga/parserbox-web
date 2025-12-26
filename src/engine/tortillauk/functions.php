<?php

class TAccountCheckerTortillauk extends TAccountChecker
{
    private string $parseUrl = 'https://loyalty.tortilla.co.uk/default.aspx';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->parseUrl, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL($this->parseUrl);

        if (!$this->http->ParseForm('form1')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('__EVENTTARGET', 'loginmenu_btnlogin');
        $this->http->SetInputValue('loginmenu_username', $this->AccountFields['Login']);
        $this->http->SetInputValue('loginmenu_password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, it looks like you\'ve found a Gremlin in our website")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Your account password has expired. Please change your password.
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Your account password has expired. Please change your password.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->parseQuestion()) {
            return true;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $url = 'https://loyalty.tortilla.co.uk/WebCalls.aspx/GetHeaderNotification';
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type'     => 'application/json; charset=utf-8',
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
        ];

        if (!$this->http->PostURL($url, null, $headers)) {
            return false;
        }

        $data = $this->http->JsonLog(null, 3, true);

        if (is_string($data['d'] ?? null)) {
            $message = strip_tags($data['d']);

            if ($message == 'Invalid username or password') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            $this->logger->error($message);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->info('parseQuestion', ['Header' => 3]);
        $question = $this->http->FindSingleNode('//div[@id = "questionRepeater_ctl01_Question_Container"]/preceding-sibling::h2');

        if (!isset($question) || !$this->http->ParseForm("frmSettings")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->sendNotification("question was entered // RR");
        $this->http->SetInputValue('questionRepeater$ctl01$freeTextAnswer', $this->Answers[$this->Question]);

        if (!$this->http->PostForm()) {
            return false;
        }
        // The answer you entered is incorrect.
//        if ($error = $this->http->FindSingleNode("//span[contains(text(), 'The answer you entered is incorrect')]")) {//todo
//            $this->AskQuestion($this->Question, $error, "Question");
//            return false;
//        }

        return true;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != $this->parseUrl) {
            $this->http->GetURL($this->parseUrl);
        }
        // Balance
        $this->SetBalance($this->http->FindSingleNode(
            '(//tr[td[contains(text(), "LOYALTY POINTS (total)")]]/td[2])[1]',
            null,
            false,
            '/([0-9]+) Point/ims'
        ));
        /*
        // EarnedToday
        $this->SetProperty('EarnedToday', $this->http->FindSingleNode(
            '//tr[td[contains(text(), "POINTS EARNED TODAY")]]/td[2]',
            null,
            false,
            '/([0-9]+) Point/ims'
        ));
        */
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode(
            '//tr[td[contains(text(), "Name")]]/td[2]'
        )));
        $this->http->GetURL('https://loyalty.tortilla.co.uk/vouchers.aspx');
        $vouchers = $this->http->FindNodes('//div[@id = "vouchers_list"]/div/a/@href');
        $vouchersBalances = $this->http->FindNodes('//div[@id = "vouchers_list"]/div/a/div[2]/div', null, '/(\d+) available/');
        foreach ($vouchers as $url) {
            $this->http->GetURL($url);
            $balance = array_shift($vouchersBalances);
            if ($balance > 1) {
                $this->sendNotification('more than 1 voucher of type found, check expiration text // BS');
            }
            $subacc = [
                'DisplayName' => $this->http->FindSingleNode('//h1'),
                'Code' => 'Voucher'.$this->http->FindPreg('/vid=(\d+)/', false, $this->http->currentUrl()),
                'Balance' => $balance,
            ];
            $expDate = $this->http->FindSingleNode('//h2[text() = "Availability"]/following-sibling::p', null, false, '/expiring on ([\w ]+:\d{2})/');
            if ($this->getTimestamp($expDate)) {
                $subacc['ExpirationDate'] = $this->getTimestamp($expDate);
            }
            $this->AddSubAccount($subacc);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
        ) {
            $this->SetBalanceNA();
        }

        /*
        $this->http->GetURL('https://loyalty.tortilla.co.uk/accountdetails_devices.aspx');
        // Card #
        $this->SetProperty('CardNumber', $this->http->FindSingleNode(
            '//ul[li[h6[contains(text(), "Loyalty Card")]]]/li[2] 
            | //ul[li[h3[contains(text(), "Loyalty Card")]]]/li[2]',
            null,
            false,
            '/[.]\s([0-9]+)/ims'
        ));
        */
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@href, "signout.ashx")]/span[contains(text(), "Sign Out")]')) {
            return true;
        }

        return false;
    }
}
