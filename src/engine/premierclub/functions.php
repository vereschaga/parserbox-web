<?php

class TAccountCheckerPremierclub extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const WAIT_TIMEOUT = 10;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome();

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://mymontcalm.montcalmcollection.com/guest?clientid=10017', [], 20);

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL('https://mymontcalm.montcalmcollection.com/guest?clientid=10017');

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="memberId"]'), self::WAIT_TIMEOUT);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id="password"]'), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[@type="submit"]'), 0);

        $this->saveResponse();

        if (!$login || !$password || !$submit) {
            return $this->checkErrors();
        }

        $login->clear();
        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $submit->click();

        return true;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[@id = 'ErrorMessage']")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Email is invalid')
                || $message == 'Password is invalid'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://mymontcalm.montcalmcollection.com/Guest/Account/Overview');
        $this->waitForElement(WebDriverBy::xpath('//tr[@id="overview_section_Profile_Name"]/td[not(contains(text(), "Member Name"))]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//tr[@id="overview_section_Profile_Name"]/td[not(contains(text(), "Member Name"))]')));

        $this->SetProperty("Number", $this->http->FindSingleNode('//tr[@id="overview_section_Profile_MemberID"]/td[not(contains(text(), "Member ID"))]'));

        $this->SetProperty("Status", $this->http->FindSingleNode('//tr[@id="overview_section_Profile_MemberLevelName"]/td[not(contains(text(), "Level"))]'));

        $this->SetProperty("MemberSince", strtotime($this->ModifyDateFormat($this->http->FindSingleNode('//tr[@id="overview_section_Profile_SignUpTime"]/td[not(contains(text(), "Member Since"))]'))));

        $this->SetProperty('LoyaltyTierNights', $this->http->FindSingleNode('//tr[@id="overview_section_Profile_Nights"]/td[not(contains(text(), "Loyalty Tier Nights"))]'));

        $this->SetProperty('TierNightsToNextLevel', $this->http->FindSingleNode('//tr[@id="overview_section_Profile_NightsToNextLevel"]/td[not(contains(text(), "Tier Nights To Next Level"))]'));

        $this->SetProperty('Stays', $this->http->FindSingleNode('//div[@id="divHotelStays"]', null, false, '/\d+/'));

        $this->http->GetURL('https://mymontcalm.montcalmcollection.com/Guest/Account/Points');
        $this->waitForElement(WebDriverBy::xpath('//tr[@id="points_section_Profile_AvailablePoints"]/td[not(contains(text(), "Available Points"))]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        $this->SetBalance($this->http->FindSingleNode('//tr[@id="points_section_Profile_AvailablePoints"]/td[not(contains(text(), "Available Points"))]'));

        $this->SetProperty('PointsEarnedThisYear', $this->http->FindSingleNode('//tr[@id="points_section_Profile_PointsEarnedThisYear"]/td[not(contains(text(), "Points Earned This Year"))]'));

        $this->SetProperty('ExpiringBalance', $this->http->FindSingleNode('//tr[@id="points_section_Profile_PointsToExpire"]/td[not(contains(text(), "Points To Expire"))]'));

        $this->http->GetURL('https://mymontcalm.montcalmcollection.com/Guest/Account/HotelStays');
        $this->waitForElement(WebDriverBy::xpath('//h2[@id="hotelstays_section_YourResv_Title"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (
            !$this->http->FindSingleNode('//tbody[@id="tbodyUpcoming"]//td[contains(text(), "No reservations activity for this time period.")]')
            || !$this->http->FindSingleNode('//tbody[@id="tbodyHistory"]//td[contains(text(), "No reservations activity for this time period.")]')
        ) {
            $this->sendNotification('refs #24294 - need to check itineraries // IZ');
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //$arg['CookieURL'] = 'http://www.premierclubrewards.org/';
        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function loginSuccessful()
    {
        $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "Logout")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//a[contains(@class, "Logout") and not(@style="display: none;")]')) {
            return true;
        }

        return false;
    }
}
