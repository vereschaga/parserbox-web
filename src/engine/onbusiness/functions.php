<?php

// refs #2037, onbusiness

class TAccountCheckerOnbusiness extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://onbusiness.britishairways.com/group/ba/home');
        $this->handleRedirectForm();

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function handleRedirectForm()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;

        if ($this->http->ParseForm(null, '//form[contains(@action, "samlsso")]')) {
            $this->http->PostForm();
        }

        $this->http->RetryCount = 2;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://onbusiness.britishairways.com/group/ba/home';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode("
                //td[contains(text(), 'On Business is unavailable at the moment due to planned maintenance.')]
                | //span[contains(text(), 'experiencing ongoing issues with the availability of our On Business platform.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Temporarily Unavailable
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug("[URL]: " . $this->http->currentUrl());
        $this->logger->debug("[CODE]: " . $this->http->Response['code']);
        // retries
        if (in_array($this->http->Response['code'], [0, 403])
            || ($this->http->Response['code'] == 200 && empty($this->http->Response['body']))) {
            throw new CheckRetryNeededException(3, 7);
        }

        if ($this->http->Response['code'] == 404) {
            $this->http->GetURL("https://www.britishairways.com/en-gb/business-travel/on-business");

            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We’re experiencing ongoing issues with the availability of')] | //span[contains(text(), 'We’re experiencing ongoing issues with the availability of')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->handleRedirectForm();

        // Access is allowed
        if ($this->http->FindNodes("//a[contains(text(), 'Log out')]")) {
            return true;
        }
        // Incorrect username - please try again.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Incorrect username - please try again.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Incorrect password - please try again.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Incorrect password - please try again.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Incorrect username or password - please try again.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Incorrect username or password - please try again.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, there is a problem with this account and we are unable to log you in. Please contact your Programme Administrator or local Customer Support Team.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, there is a problem with this account and we are unable to log you in.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, there was a problem. Please try again.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, there was a problem. Please try again.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Password expired. Please set up new password
        if ($message = $this->http->FindSingleNode('//h1[normalize-space() = "Password expired. Please set up new password"]')) {
            $this->throwProfileUpdateMessageException();
        }

        // provider error
        if ($this->http->FindSingleNode('//div[@class="portlet-content" and contains(., "You do not have permission to access the requested resource.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->Response['code'] === 302
            && in_array($this->http->currentUrl(), [
                'https://onbusiness.britishairways.com/group/ba/home',
                'https://onbusiness.britishairways.com/web/servlet/genericRedirect_homePage?p_p_id=58&p_p_lifecycle=0&_58_redirect=%2Fgroup%2Fba%2Flogin-details',
            ])
       ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://onbusiness.britishairways.com/group/ba/home");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg('/firstName:"([^\"]+)/') . " " . $this->http->FindPreg('/lastName:"([^\"]+)/')));
        // Company Membership
        $this->SetProperty("Number", $this->http->FindPreg('/companyMembershipNumber:"([^\"]+)/'));
        // Company name
        $this->SetProperty("CompanyName", $this->http->FindPreg('/companyName:"([^\"]+)/'));
        // Balance - Points available
        $this->SetBalance($this->http->FindPreg('/redemptionPointsBalance:(\d+)/'));
        // Airline expenditure
        $this->SetProperty("AirlineExpenditure", $this->http->FindPreg('/expenditureBalancePrgBaseCurrency:"([^\"]+)/'));
        // Your tier
        $this->SetProperty("Tier", $this->http->FindPreg("/eliteTiers:\[\{name:\"([^\"]+)\"/"));
        // Status expiration
        $this->SetProperty("StatusExpiration", $this->http->FindPreg("/eliteTiers:\[\{name:\"[^\"]+\",expirationDate:\"([^\"]+)\"\}/"));

        // Points expiring
        preg_match_all('/\{amount:(?<points>\d+),expirationDate:\"(?<expirationDate>[^\"]+)\"\}/ims', $this->http->Response['body'], $matches, PREG_SET_ORDER);
        $this->http->Log("<pre>" . var_export($matches, true) . "</pre>", false);

        foreach ($matches as $match) {
            $date = $this->ModifyDateFormat($match['expirationDate']);

            if (strtotime($date) && (!isset($exp) || $exp > strtotime($date))) {
                $exp = strtotime($date);

                // https://redmine.awardwallet.com/issues/19593#note-12
                if ($exp == 1672444800) {
                    $this->logger->notice("Extend Expiration Date by rules to 31 Sep 20222");
                    $exp = 1672444800;
                }

                $this->SetExpirationDate($exp);
                // Points to Expire
                $this->SetProperty("PointsToExpire", $match['points']);
            }// if (($d !== false) && ($miles != '0'))
        }// foreach ($matches as $match)
    }
}
