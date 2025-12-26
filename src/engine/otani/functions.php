<?php

class TAccountCheckerOtani extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://member.newotani.co.jp/v/?VID=user.extCommon.top&OP=view", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://member.newotani.co.jp/?locale=en");

        if (!$this->http->ParseForm("publicForm")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('LOGIN_ID', $this->AccountFields['Login']);
        $this->http->SetInputValue('LOGIN_PASSWORD', $this->AccountFields['Pass']);
        $this->http->SetInputValue('LOGIN', "Log+in");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//ul[@class = "list-error"]/li')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Entered email address (log-in ID) or password is incorrect.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Membership ID
        $this->SetProperty('MemberId', $this->http->FindSingleNode("//dl[contains(@class, 'header-member')]/dt"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//dl[contains(@class, 'header-member')]/dd")));

        $balancePage = $this->http->FindSingleNode('//header[@data-toggle="sticky-onscroll"]//li[contains(@class, "nav-site-points")]/a/@href');
        $voucherPage = $this->http->FindSingleNode('//header[@data-toggle="sticky-onscroll"]//li[contains(@class, "nav-site-ticket")]/a/@href');

        if (!$balancePage || !$voucherPage) {
            $this->logger->error("some link not found");

            return;
        }

        $this->http->NormalizeURL($balancePage);
        $this->http->NormalizeURL($voucherPage);

        $this->http->GetURL($balancePage);
        // Balance - Your current points
        $this->SetBalance($this->http->FindSingleNode('//div[@class = "display-point"]/strong'));
        // Expiration date
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $expNodes = $this->http->XPath->query('//div[@id = "extPointMyPageListEntire"]/table//tr[td]');
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");

        foreach ($expNodes as $expNode) {
            // Expiration Date
            $expirationDate = $this->http->FindSingleNode('td[1]', $expNode);

            if (!isset($exp) || $exp < strtotime($expirationDate)) {
                $exp = strtotime($expirationDate);

                $this->SetExpirationDate($exp);
                // Expiring Balance
                $this->SetProperty('ExpiringBalance', $this->http->FindSingleNode('td[2]/text()[1]', $expNode));
            }
        }// foreach ($expNodes as $expNode)

        // Your current e-Vouchers
        $this->logger->info('e-Vouchers', ['Header' => 3]);
        $this->http->GetURL($voucherPage);
        $vouchers = $this->http->FindSingleNode('//div[@class = "display-point"]/strong');
        $this->logger->debug("Total vouchers: {$vouchers}");

        if ($vouchers > 0) {
            $this->sendNotification("refs #23124 - vouhers were found // RR");
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (strstr($this->http->currentUrl(), 'user.extCommon') && $this->http->FindSingleNode('//a[contains(@href, "logout")]')) {
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
