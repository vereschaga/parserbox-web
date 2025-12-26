<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSunshine extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = "https://www.sunshinerewards.com/members.php";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
        $this->http->SetProxy($this->proxyReCaptcha());
        */
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
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'members.php')]")) {
            if ($this->http->Response['code'] == 503) {
                throw new CheckRetryNeededException(3);
            }

            return false;
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $md5salt = "737697115";
        $md5res = md5($this->AccountFields['Pass'] . $md5salt);
        $this->http->SetInputValue("md5salt", $md5salt);
        $this->http->Form["submit"] = "Submit";
        $this->http->Form["action"] = "login";
        $this->http->SetInputValue("md5pwd", $md5res);
        $this->http->SetInputValue("md5pwd_utf", $md5res);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($message = $this->http->FindPreg("/(Bad Username or password, please try again)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Balance
        $this->SetBalance($this->http->FindPreg("/Balance: ([^<]*)</ims"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/Welcome back,\s*([^!<]*)!?/ims")));

        // Page "Edit Your Account"
        $this->http->GetURL("https://www.sunshinerewards.com/info.php");
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode('//td[contains(text(), "Membership:")]/../td[2]', null, true, '/([a-zA-Z]+) \(/ims'));
        // Name
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[@name = 'fname']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@name = 'sname']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }

        // Page "Current Earnings"
        $this->http->GetURL("https://www.sunshinerewards.com/stats.php");
        // Total Earnings This Quarter
        $this->SetProperty("EarningsThisQuarter", $this->http->FindSingleNode("//td[b[contains(text(), 'Total Earnings This Quarter:')]]/following-sibling::td[1]"));
        // Total Earnings This Year
        $this->SetProperty("EarningsThisYear", $this->http->FindSingleNode("//td[b[contains(text(), 'Total Earnings This Year:')]]/following-sibling::td[1]"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        return false;
    }
}
