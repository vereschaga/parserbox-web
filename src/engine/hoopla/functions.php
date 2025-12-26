<?php

class TAccountCheckerHoopla extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://hoopladoopla.com/account/dashboard';

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams();

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
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
        $this->http->GetURL("https://hoopladoopla.com/login");

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'https://hoopladoopla.com/login')]")) {
            return false;
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//p[contains(@class, 'is-error')][contains(text(), 'These credentials do not match our records.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//div[contains(@class, 'card__account')]/h2"));
        // Total cash back
        $this->SetProperty("ClearedEarnings", implode('', $this->http->FindNodes("//p[contains(text(), 'Total cash back')]/preceding-sibling::div/text()")) . "." . $this->http->FindSingleNode("//p[contains(text(), 'Total cash back')]/preceding-sibling::div/span"));
        // Balance - Cash to be paid
        $this->SetBalance(implode('', $this->http->FindNodes("//p[contains(text(), 'Cash to be paid')]/preceding-sibling::div/text()")) . "." . $this->http->FindSingleNode("//p[contains(text(), 'Cash to be paid')]/preceding-sibling::div/span"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode('//h1[contains(text(), "Your email address needs to be verified.")]')) {
                $this->throwProfileUpdateMessageException();
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }
}
