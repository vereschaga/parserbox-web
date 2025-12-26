<?php

//  ProviderID: 1298

class TAccountCheckerMycompagnie extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
//        if ($this->loginSuccessful()) {
//            return true;
//        }

        return false;
    }

    public function LoadLoginForm()
    {
        // The username or password wrong
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('The username or password wrong', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://booking.lacompagnie.com/Zenith/FrontOffice/(S(1acd97ab033a4efa9f8e74a3f28a19d0))/lacompagnie/en-GB/Customer/Login', [], 120);
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'Customer/Login')]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('EmailAddress', $this->AccountFields['Login']);
        $this->http->SetInputValue('Login', $this->AccountFields['Login']);
        $this->http->SetInputValue('LoginWithEmail', "true");
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.lacompagnie.com';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our website is currently unavailable. We invite you to retry in a moment.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // The username or password wrong
        if ($message = $this->http->FindPreg('/\"error\":\{"title":"Information","content":"((?:The username or password\s*wrong|The username or password is wrong\.\s*))"/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        // Balance - acquired miles
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'acquired mile')]", null, true, "/(.+) acquired mile/"));
        // Qualification miles
        $this->SetProperty('QualificationMiles', $this->http->FindSingleNode('//h3[contains(text(), "Qualification miles")]/following-sibling::p[1]', null, true, "/([\d\.\, ]+) mile/"));
        // Qualification flights
        $this->SetProperty('QualificationFlights', $this->http->FindSingleNode('//h3[contains(text(), "Qualification miles")]/following-sibling::p[1]', null, true, "/([\d\.\, ]+) flight/"));
        // Number
        $this->SetProperty('MemberNumber', $this->http->FindSingleNode('//div[contains(@class, "customer-info")]/p[1]', null, false, '/\(([^\)]+)/'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[contains(@class, "customer-info")]/p[1]', null, false, '/(?:Mrs?\.?|Ms\.?)\s*([^\(]+)/')));
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode('//p[strong[contains(text(), "Status")]]', null, false, '/:\s*([^<]+)/'));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'LogOff')]")) {
            return true;
        }

        return false;
    }
}
