<?php

// refs #2053

class TAccountCheckerSunoco extends TAccountChecker
{
    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams();

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.myaplus.com/my-aplus/sign-in/");

        if (!$this->http->ParseForm("sign-in")) {
            return $this->checkErrors();
        }

        if (!strstr($this->AccountFields['Login'], "@")) {
            throw new CheckException("Sunoco (APlus Rewards) website is asking you to upgrade your account, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm([], 280)) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        //# Access is allowed
        if ($this->http->FindSingleNode("(//a[contains(@href, 'sign-out')]/@href)[1]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//p[@class = 'error']")) {
            $this->logger->error($message);

            if (stristr($message, 'The email address and password you entered could not be found.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        // You have not activated your account yet.
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'You have not activated your account yet.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Fatal error: Call to undefined function curl_init() in /var/www/sunoco/sys/expressionengine/third_party/gosunoco/mod.gosunoco.php on line 148
        if ($this->http->FindPreg("/(?:Fatal error: Call to undefined function curl_init\(\) in |^BREAKERRRR$)/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - APlus Rewards balance
        if (!$this->SetBalance($this->http->FindSingleNode("//h3[contains(text(), 'Available Balance ')]/span", null, true, '/\$([\d\.\,]+)/')) && $this->http->FindSingleNode("//h3[contains(text(), 'Available Balance ')]/span") == '$-- per gal.') {
            $this->SetBalanceNA();
        }
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'as-content']/h4")));
        //# Account Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//h3[contains(text(), 'Account Number')]/span"));
        //# Expiring Balance
        $expiringBalance = $this->http->FindSingleNode("//h3[contains(text(), 'Expiring')]/span", null, true, '/([\$][\d\.\,]+)/');
        $this->SetProperty("ExpiringBalance", $expiringBalance);
        //# Expiration Date
        $d = $this->http->FindSingleNode("//h3[contains(text(), 'Expiring')]", null, true, "/Expiring\s*(\d{2}\/\d{2}\/\d{2})/ims");
        $this->logger->debug("Expiration Date: " . $d . " / " . strtotime($d));
        $d = strtotime(trim($d));

        if (preg_replace('/[^\d\.\,\-]/', '', $expiringBalance) > 0 && $d !== false) {
            $this->SetExpirationDate($d);
        }

        /*
         * To check your APlus Rewards card balance online and get even more special deals register your card now.
         * To get an APlus Rewards card visit an APlus location near you.
         */
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !empty($this->Properties['Name'])
            && ($message = $this->http->FindPreg("/(To check your APlus Rewards card balance online and get even more special deals\s*<a[^>]+>register your card now<\/a>)/ims"))) {
            $this->ErrorCode = ACCOUNT_WARNING;
            $this->ErrorMessage = 'To check your APlus Rewards card balance online and get even more special deals register your card now.';
        }
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h2[contains(text(), "500 - Internal server error.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
