<?php

class TAccountCheckerTrident extends TAccountChecker
{
    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerTridentSelenium.php";

        return new TAccountCheckerTridentSelenium();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.tridentprivilege.com/index.aspx");

        if (!$this->http->ParseForm('frmLogin')) {
            return $this->checkErrors();
        }
        $this->http->FormURL = "http://www.tridentprivilege.com/index.aspx";
        $this->http->SetInputValue("txtUserName", $this->AccountFields['Login']);

        $hdnKey = $this->http->Form["hdnKey"];
        //		$hdnKey = '6770805376287962';
        //		$this->http->Log("OyCIlx9c0++AvR0vZ76YSQ==");
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC), MCRYPT_RAND);
        $cryptedPass = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $hdnKey, $this->AccountFields['Pass'], MCRYPT_MODE_CBC, $iv));
        $this->http->Log("v. 3");
        //		$cryptedPass = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $hdnKey, $this->AccountFields['Pass'], MCRYPT_MODE_CBC));

        $this->http->SetInputValue("txtPassword", $cryptedPass);
        //		return;
        $this->http->SetInputValue("ImageButton1.x", "1");
        $this->http->SetInputValue("ImageButton1.y", "1");

        return true;
    }

    public function checkErrors()
    {
        // Server Error in '/' Application
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")) {
            return true;
        }

        $error = $this->http->FindSingleNode("//span[@id = 'lbl_Message']");

        if (isset($error) && !empty($error)) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//span[@id = 'lbl_ErrorMsg']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//td[contains(text(), 'Member Since')]/following-sibling::td[2]"));
        //# Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//p[contains(text(), 'Name')]/../p[2]"));
        //# Member Tier
        $this->SetProperty("MemberTier", $this->http->FindSingleNode("//td[contains(text(), 'Member Tier')]/following-sibling::td[2]"));
        //# Points Balance
        $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'Points Balance')]/following-sibling::td[2]"));

        if (preg_match('/Additional (\d+) stays or (\d+) points/ims', $this->http->FindSingleNode('//b[contains(text(), "Upgrade to ") and contains(text(), " tier with")]/following-sibling::node()[2][self::text()]'), $matches)) {
            //# Stays to Next Level
            $this->SetProperty('StaysToNextLevel', $matches[1]);
            //# Points to Next Level
            $this->SetProperty('PointsToNextLevel', $matches[2]);
        }
        //# Expiry Date
        if ($this->Balance > 0) {
            $expire = $this->http->FindSingleNode("//td[contains(text(), 'Expiry Date')]/following-sibling::td[2]");
            $this->SetExpirationDate(strtotime($expire));
            $this->http->Log("Expiry Date: " . var_export($expire, true), true);
            //# Points Expiring
            $this->SetProperty("PointsExpiring", $this->http->FindSingleNode("//td[contains(text(), 'Points expiring')]/following-sibling::td[2]"));
        }
    }
}
