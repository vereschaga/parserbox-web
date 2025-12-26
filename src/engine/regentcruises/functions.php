<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerRegentcruises extends TAccountChecker
{
    use ProxyList;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.rssc.com/myaccountlogin.aspx?ReturnUrl=%2fmyaccount%2f';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');

        // crocked server workaround
        $this->http->SetProxy($this->proxyDOP());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.rssc.com/myaccount/', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Login must be correct email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Invalid e-mail and/or password', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.rssc.com/myaccountlogin.aspx?ReturnUrl=%2fmyaccount%2f');

        if (!$this->http->ParseForm('form1')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('uxMal$uxRegister$uxLoginEmailAddressTextbox', $this->AccountFields['Login']);
        $this->http->SetInputValue('uxMal$uxRegister$uxLoginPasswordTextbox', $this->AccountFields['Pass']);
        $this->http->SetInputValue('uxMal$uxRegister$uxRememberMeCheckbox', 'on');
        $this->http->SetInputValue('__EVENTTARGET', 'uxMal$uxRegister$uxLoginButton');

        $this->http->setCookie("_abck", "C1028609E2349B8EA965711724E3DD45~0~YAAQl2rcFzx41MmSAQAAtprb0gwVUuGPKwvsIf2kv1BzZNERo+64lrXNb7/vYRl8HLv4Rzpfw7lHzKTYJpH+wmGXIjO8yS4yF1vI/IsocK4ONmuM99gNyqY6qO6L/kLAfVf3iaAZZSZJSnNIE5u+BM5iYXN7PCw/jnfBcbcpgtGOx84lvmztaYA38DPe8FnINxZ5mhipcwB+3L3Pbwxhz8FwIdwN5A7LZKAO2qcbAfmUQh4Em6mDWVhbdBXWD9Ovs+jD2T/DZ6uMRpkHj13oCtu3pKET4YqXK4Dh9/zZJvLWjclc3uQqRcj50Qzz2KDDIiEfbKBpfaY8NIRfqkt+4VEsAd6aTbb0yHktlAuuTwqFNXireCtQO3gGi6cNgwfso4ccSgI1q+6juSSye4swFYVcvezFNV+yX+EKS0umt14xzpslPuKk1xn7FwJO/r3hZdSk9HZTcdCUSLv56JNjX+rQOQ==~-1~||0||~-1"); // todo: sensor_data workaround

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

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Email and/or password invalid")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Nights cruised
        $this->SetBalance($this->http->FindSingleNode('//div[@id="cruiseInfo"]//div[@class="myaccountSideboxInside"]//td[contains(., "Nights cruised:")]/following-sibling::td[1]'));

        // Name
        $name = beautifulName($this->http->FindSingleNode('(//div[@id="cruiseInfo"]//div[@class="myaccountSideboxInside"]/p)[1]'));
        $name = str_replace(':', '', $name);
        $this->SetProperty('Name', $name);

        // Tier Level
        $this->SetProperty('TierLevel', $this->http->FindSingleNode('//div[@id="cruiseInfo"]//div[@class="myaccountSideboxInside"]//td[contains(., "Tier Level:")]/following-sibling::td[1]'));

        // Reward Nights
        $this->SetProperty('RewardNights', $this->http->FindSingleNode('//div[@id="cruiseInfo"]//div[@class="myaccountSideboxInside"]//td[contains(., "Reward Nights:")]/following-sibling::td[1]'));

        // Nights until next level
        $this->SetProperty('NightsUntilNextLevel', $this->http->FindSingleNode('//div[@id="cruiseInfo"]//div[@class="myaccountSideboxInside"]//td[contains(., "Nights until next level:")]/following-sibling::td[1]'));

        $this->http->GetURL('https://www.rssc.com/myaccount/bookedcruises.aspx');

        if ($this->http->FindNodes("//div[@id='uxBookedCruisesList_uxValidationSummary']//*")
            || count($this->http->FindNodes("//div[@id='contentText']/*")) > 11) {
            $this->sendNotification("refs #12319: Check Itineraries // MI");
        }
    }

    public function ParseItineraries()
    {
        return [];
        $this->http->GetURL('https://www.rssc.com/myaccount/savedcruises.aspx');
        $urls = $this->http->FindNodes("//h3/a[contains(@href,'/cruises/') and contains(text(),'View Details')]/@href");

        foreach ($urls as $url) {
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
            $this->parseItinerary($this->http->FindPreg('#/cruises/(\w+)/summary#', false, $url));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//div[@id="cruiseInfo"]//div[@class="myaccountSideboxInside"]//td[contains(text(), "Nights cruised:")]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseItinerary($conf)
    {
        $this->logger->notice(__METHOD__);
        $cruise = $this->itinerariesMaster->createCruise();
        $cruise->general()->confirmation($conf);
        $this->logger->info("Parse Itinerary #{$conf}", ['Header' => 3]);
        //$cruise->general()->status();
    }
}
