<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerOlympicair extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->getURL('https://www.olympicair.com/en/Travelair/Dashboard');

        if (!$this->http->ParseForm('mainform')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('content_0$phtravelairleftcontent_0$UserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('content_0$phtravelairleftcontent_0$Password', $this->AccountFields['Pass']);
        $this->http->Form['content_0$phtravelairleftcontent_0$Login'] = 'LOGIN';
        $this->http->Form['ScriptManager1'] = 'ScriptManager1|content_0$phtravelairleftcontent_0$Login';
        $this->http->Form['__ASYNCPOST'] = 'true';
        $this->http->Form['content_0$phwidgetholder_2$ddlSeatClasses'] = '{81C19572-35A3-49D5-898E-226F4B8DA653}';
        $this->http->Form['content_0$phwidgetholder_3$ddlSeatClasses'] = '{81C19572-35A3-49D5-898E-226F4B8DA653}';

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.olympicair.com/en/Travelair/Dashboard';
        $arg['SuccessURL'] = 'https://www.olympicair.com/en/Travelair/Dashboard';

        return $arg;
    }

    public function checkErrors()
    {
        // 500 error
        if (strstr($this->http->currentUrl(), 'layouts/500.html?aspxerrorpath')) {
            $this->http->GetURL("https://www.olympicair.com");

            if (strstr($this->http->currentUrl(), 'layouts/500.html?aspxerrorpath')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Login successful
        if ($this->http->FindPreg("/pageRedirect\|\|%2fen%2fTravelair%2fDashboard/ims")) {
            $this->http->GetURL("https://www.olympicair.com/en/Travelair/Dashboard");

            return true;
        }
        //# Login failed
        if ($message = $this->http->FindPreg("/(Login failed)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# The member ID you entered is incorrect
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'The member ID you entered is incorrect')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Your member ID is comprised of 10 digits
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Your member ID is comprised of 10 digits')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# The PIN number you entered is incorrect
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'The PIN number you entered is incorrect')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# There was a network error. Please try again later.
        if ($message = $this->http->FindPreg("/(There was a network error\.\s*Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# You must activate your account before accessing your profile
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Activate Member Profile')]")) {
            throw new CheckException('You must activate your account before accessing your profile', ACCOUNT_PROVIDER_ERROR);
        }
        //# Your account has been deactivated. Please contact Travelair Club
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Your account has been deactivated. Please contact Travelair Club.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The Together program has been ended. For more details, please contact Travelair Club.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'The Together program has been ended. For more details, please contact Travelair Club.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Login failed.
        if ($message = $this->http->FindPreg("/((pageRedirect\|\|\%2flayouts\%2f500\.html\%3faspxerrorpath\%3d\%2fen\%2fTravelair\%2fDashboard\|))/ims")) {
            throw new CheckException("Login failed.", ACCOUNT_PROVIDER_ERROR);
        }

        // As of November 24, 2014, the new Miles+Bonus will be the common loyalty program for both Olympic Air and Aegean.
        if ($this->http->FindPreg("/pageRedirect\|\|%2fen%2fTravelair%2fInfo%2fTravelai/ims")) {
            $this->http->GetURL("https://www.olympicair.com/en/Travelair/Info/Travelair");

            if ($message = $this->http->FindPreg("/As of November 24, 2014, the new Miles\+Bonus will be the common loyalty program for both Olympic Air and Aegean\./ims")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class= 'loggedInIns']/h1")));
        //# MEMBER ID
        $this->SetProperty("CardNr", $this->http->FindSingleNode("//div[contains(text(), 'MEMBER ID')]/following-sibling::div[1]"));
        //# CURRENT TIER
        $this->SetProperty("Tier", $this->http->FindSingleNode("//div[contains(text(), 'CURRENT TIER IS')]/following-sibling::div[1]"));
        //# Balance - MILES
        if (!$this->SetBalance($this->http->FindSingleNode("//div[@id = 'content_0_phwidgetholder_1_pnlOuter']//div[contains(text(), 'YOU HAVE COLLECTED')]/following-sibling::div[1]"))) {
            $this->SetBalance($this->http->FindSingleNode("//div[@id = 'content_0_phwidgetholder_0_onlyVirtual']//div[contains(text(), 'YOU HAVE COLLECTED')]/following-sibling::div[1]"));
        }
        //# miles to reach next tier
        $this->SetProperty("MilestoUpgrade", $this->http->FindSingleNode("//strong[contains(text(), 'miles to reach')]/parent::div", null, true, "/[\d\.\,]+/ims"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindSingleNode("//h1[contains(text(), 'Please fill in all form fields as well as at least one contact phone')]")
            && $this->http->currentUrl() == 'https://www.olympicair.com/en/Travelair/Dashboard/Profile') {
            throw new CheckException("You need to update your profile.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/
    }
}
