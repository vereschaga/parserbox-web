<?php

class TAccountCheckerStarplex extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://starrewards.starplexcinemas.com/login/");

        if (!$this->http->ParseForm('loginform')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('log', $this->AccountFields['Login']);
        $this->http->SetInputValue('pwd', $this->AccountFields['Pass']);
        $this->http->Form['wp-submit'] = 'Log In';
        unset($this->http->Form['_wp_original_http_referer']);
        unset($this->http->Form['rememberme']);

        return true;
    }

    public function checkErrors()
    {
        //# 500 - Internal server error
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), '500 - Internal server error.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Starplex is now part of AMC Theatres!
        if ($message = $this->http->FindPreg("/(Starplex is now part of AMC Theatres!)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//p[@class = 'error']", null, true, "/ERROR:\s*(.*)Lost/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//p[@class = 'error']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Next Reward
        $this->SetProperty("NextReward", $this->http->FindSingleNode("//div[contains(@id, 'points')]/p[contains(@class, 'next')]", null, true, '/([\d\.,]+)$/ims'));
        // Current Points
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@id, 'points')]/p[contains(@class, 'current')]", null, true, '/([\d\.,]+)$/ims'));

        // Name
        $this->http->GetURL("http://starrewards.starplexcinemas.com/my-account/edit-profile/");
        $name = CleanXMLValue($this->http->FindSingleNode("//input[@name='firstName']/@value")
                    . ' ' . $this->http->FindSingleNode("//input[@name='lastName']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // Card#
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//h3[contains(text(), 'Card#:')]", null, true, "/Card#\s*:\s*([^<]+)/"));
    }
}
