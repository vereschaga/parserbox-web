<?php

class TAccountCheckerRewards extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.idine.com/login.htm');

        if (!$this->http->ParseForm('loginform')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('loginId', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->Form['remember'] = 'on';
        $this->http->Form['x'] = 26;
        $this->http->Form['y'] = 7;
        $this->http->Form['loginType'] = 'standard';
        $this->http->Form['showMessage'] = 'Y';
        $this->http->setDefaultHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');
        $this->http->setDefaultHeader('Accept-Language', 'ru-ru,ru;q=0.8,en-us;q=0.5,en;q=0.3');

        return true;
    }

    public function checkErrors()
    {
        //# The site is down for maintenance
        if ($message = $this->http->FindPreg("/<title>Sorry, the site is down for maintenance<\/strong>/ims")) {
            throw new CheckException("Sorry, the site is down for maintenance", ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request
        if ($message = $this->http->FindPreg("/An error occurred while processing your request/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException("Sorry, the site is down for maintenance", ACCOUNT_PROVIDER_ERROR);
        }
        //# HTTP Status 404
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 404')]")) {
            throw new CheckException("Sorry, the site is temporarily unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/

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

        if ($message = $this->http->FindPreg("/<div id=\"txt1\" class=\"mar_t5\">\s*<strong>([^<]+)<\/strong>/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/Login attempt failed/ims")) {
            throw new CheckException("Login attempt failed", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/Incorrect <strong>Login ID<\/strong> and\/or <strong>Password/ims")) {
            throw new CheckException("Incorrect Login ID and/or Password", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/<span id=\"loginErrorMsg\">([^<]+)</ims")) {
            if (strstr($message, 'Account temporarily locked')) {
                throw new CheckException('Account temporarily locked due to too many login attempts.', ACCOUNT_LOCKOUT);
            } else {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        // Create a New Password
        if ($this->http->FindSingleNode("//div[contains(text(), 'Confirm New Password*')]")) {
            throw new CheckException("iDine website is asking you to create a new password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Balance - Benefits Building to a Reward Card
        if ($balance = $this->http->FindPreg("/<b>\s*Benefits Building to a Check\s*<\/b>\s*:\s*\\$([^<]+)</ims")) {
            $this->SetBalance($balance);
        } elseif ($balance = $this->http->FindPreg("/\s*Benefits Building to a Reward Card\s*<\/b>:\s*([^<]+)/ims")) {
            $balance = str_replace("\$", "", $balance);
            $this->SetBalance($balance);
        }

        //# Spend Since Anniversary
        $this->SetProperty('Anniversary', $this->http->FindPreg("/<b>\s*Spend Since Anniversary\s*<\/b>\s*:([^<]+)</ims"));
        //# Name
        $name = $this->http->FindSingleNode("//td[@class = 'textwhite f12 bold pad_t5 pad_l11 left allcaps']");
        $this->SetProperty('Name', beautifulName($name));
        //# Account #
        $this->SetProperty('Number', $this->http->FindPreg("/<b>\s*Account #:\s*<\/b>\s*([^<]+)</ims"));
        //# Status
        $this->SetProperty('Status', $this->http->FindSingleNode("//span[@class = 'bold pad_l11 f11']"));

        //# Get page with Last Activity /*checked*/
        $this->http->GetURL('https://www.idine.com/myaccount/rewards.htm#zoom');

        if (!$this->http->ParseForm('loginform')) {
            return false;
        }

        $this->http->Form['loginId'] = $this->AccountFields['Login'];
        $this->http->Form['password'] = $this->AccountFields['Pass'];
        $this->http->Form['remember'] = 'on';
        $this->http->Form['x'] = 26;
        $this->http->Form['y'] = 7;
        $this->http->Form['loginType'] = 'standard';
        $this->http->Form['showMessage'] = 'Y';
        $this->http->setDefaultHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');
        $this->http->setDefaultHeader('Accept-Language', 'ru-ru,ru;q=0.8,en-us;q=0.5,en;q=0.3');
        $this->http->PostForm();

        //# Last Activity + Expiration Date
        $xpath = $this->http->XPath;
        $nodes = $xpath->query('//tr[@id="id1"]/td');

        if ($nodes->length) {
            $this->SetProperty('LastActivity', trim($nodes->item(0)->nodeValue));
            $expiration = strtotime($nodes->item(0)->nodeValue . '+1 year');

            if ($expiration) {
                $this->SetExpirationDate($expiration);
            }
        }
    }
}
