<?php

class TAccountCheckerVolkswagen extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.vw-club.de/");

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Access forbidden!')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        $this->http->GetURL("https://www.vw-club.de/mikos/files/ajax_login.php");

        if (!$this->http->ParseForm("mikos_login")) {
            return false;
        }

        $this->http->Form["username"] = $this->AccountFields['Login'];
        $this->http->Form["password"] = $this->AccountFields['Pass'];
        $this->http->Form["class"] = 'KIS';
        $this->http->Form["kis_action"] = 'doLogin';

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        $access = $this->http->FindPreg("/Login erfolgreich/ims");

        if (isset($access)) {
            return true;
        }

        if ($message = $this->http->FindPreg("/response_error/ims")) {
            throw new CheckException('Bad username or password', ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $pageRedirect = $this->http->FindPreg("/top\.location\.href=\'([^\']+)\'/ims");
        $this->http->GetURL($pageRedirect);
        $this->SetProperty("Name", $this->http->FindSingleNode('//div[@id="topbox"]/div[1]/a[1]'));
        $this->SetBalance($this->http->FindPreg("/Sie haben zurzeit[^<]*<b>\s*([0-9]+)/ims"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'http://www.vw-club.de/';

        return $arg;
    }
}
