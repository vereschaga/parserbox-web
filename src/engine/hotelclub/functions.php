<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerHotelclub extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->LogHeaders = true;

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            if (rand(1, 3) > 1) {
                $this->http->SetProxy($this->proxyDOP());
            } else {
                $this->http->Log(">>> no proxy");
            }
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.hotelclub.com/en_au/account/myclub";
        //$arg["PostValues"]['Submit'] = $arg["PostValues"]['submit'];
        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setCookie("AustinLocale", "en_AU", "www.hotelclub.com", "/", strtotime("Wed, 18-11-2034 10:57:52 GMT"));
        $this->http->GetURL("https://www.hotelclub.com/account/myclub");

        if (!$this->http->ParseForm()) {
            //# Proxy
            if ($message = $this->http->FindPreg("/(Unusual traffic has been identified from your IP Address)/ims")) {
                $this->logger->error($message);
            }

            return $this->checkErrors();
        }
        $this->http->SetInputValue("models['userName'].userName", $this->AccountFields['Login']);
        $this->http->SetInputValue("models['loginPasswordInput'].password", $this->AccountFields['Pass']);
        $this->http->Form['_eventId_submit'] = 'Sign in';

        return true;
    }

    public function checkErrors()
    {
        // Maintenance
        if ($error = $this->http->FindSingleNode('//p[contains(text(), "We\'re making updates and improvements to the site")]')) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, we’re currently working to improve the site.
        if ($error = $this->http->FindSingleNode('//strong[contains(text(), "Sorry, we’re currently working to improve the site")]')) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($this->http->FindSingleNode('//b[contains(text(), "Http/1.1 Service Unavailable")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable")]')
            // An error occurred while processing your request
            || ($this->http->FindPreg('/An error occurred while processing your request\./') && $this->http->Response['code'] == 504)) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Successful login
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")) {
            return true;
        }

        if ($error = $this->http->FindPreg("/Your membership account is currently inactive/ims")) {
            throw new CheckException($error . " Please contact us by e-mail at <a href='mailto:membership@hotelclub.com'>membership@hotelclub.com</a>", ACCOUNT_LOCKOUT);
        }

        if ($error = $this->http->FindSingleNode("//*[@class='signInForm']/p[contains(@class, 'error message')]")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        if ($error = $this->http->FindSingleNode("//span[contains(text(), 'Sorry, we were unable to finish processing your reservation')]")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        //# Email or password invalid
        if ($error = $this->http->FindSingleNode("//*[@class = 'error']")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->getURL('https://www.hotelclub.com/account/myclub');
        // Name
        $name = $this->http->FindSingleNode("//div[@id='header']//ul[@class='login']/*[@class='welcomeText']", null, true, "/Welcome \s*(.+)/ims");
        $this->SetProperty("Name", beautifulName($name));
        // Balance - Member Rewards balance
        $this->SetBalance($this->http->FindSingleNode("//div[@id='header']//ul[@class='login']/*[@class='loyaltyInfo']", null, true, "/\s*([\d\.\,]+)\s*Member Rewards/ims"));
        // Tier
        $this->SetProperty("Status", $this->http->FindSingleNode("//li[@class = 'loyaltyTier']", null, true, "/(.+) Member/"));
        // Expiration date
        $expNodes = $this->http->XPath->query("//td[contains(@class, 'expirationDate futureExpiration')]");

        for ($i = 0; $i < $expNodes->length; $i++) {
            $date = CleanXMLValue($expNodes->item($i)->nodeValue);
            $this->http->Log("Exp date: $date / " . strtotime($date));
            $date = strtotime($date);

            if (!isset($exp) || ($date > time() && $date < $exp)) {
                $exp = $date;
                $this->SetExpirationDate($exp);
            }// if (!isset($exp) || ($date > time() && $date < $exp))
        }// for ($i = 0; $i < $expNodes->length; $i++)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name']) || $this->http->FindSingleNode("//span[@class = 'userName']")) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    /*
     * these itineraries like as orbitz, cheaptickets, ebookers, hotelclub, expedia, travelocity itineraries
     *
     * YOU NEED TO CHECK ALL PARSERS
     */
}
