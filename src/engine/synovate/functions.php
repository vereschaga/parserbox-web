<?php

class TAccountCheckerSynovate extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->getURL('https://www.globalopinionpanels.com/login');
        //58_?
        $this->http->FormURL = "https://www.globalopinionpanels.com/login?p_p_id=58&p_p_action=1&p_p_state=normal&p_p_mode=view&p_p_col_id=column-1&p_p_col_pos=0&p_p_col_count=2&_58_struts_action=%2Flogin%2Fview&_58_cmd=update";
        $this->http->SetFormText('_58_rememberMe=false&_58_login=veresch&_58_password=067ff1cb946836af3340def07f156c1e600cfe41', '&', true, true);
        $this->http->Form['_58_login'] = $this->AccountFields['Login'];
        $this->http->Form['_58_password'] = sha1($this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        if ($this->http->FindPreg("/<strong>You currently have /")
            || $this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(),"Member Login")]')) {
            throw new CheckException('Invalid username and/or password, please try again', ACCOUNT_INVALID_PASSWORD);
        }

        //# System is currently unavailable
        if ($message = $this->http->FindPreg("/but we are currently in the midst of making some improvements to our site/ims")) {
            throw new CheckException("We are currently in the midst of making some improvements to our site.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Error 404
        if ($message = $this->http->FindSingleNode("//title[contains(text(), 'Not Found')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->getURL('https://www.globalopinionpanels.com/myhome');
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'wb-welcome']/span", null, true, '/, (.*) !*/ims')));
        //# Balance - points
        $this->SetBalance($this->http->FindPreg('/tg\.innerHTML\s*=\s*"(.*)";/ims'));
    }
}
