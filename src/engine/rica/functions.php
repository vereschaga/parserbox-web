<?php

class TAccountCheckerRica extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.rica-hotels.com/benefits-with-rica/rica-points/?lcn=en");
        /*
         * On October 1, Rica Points and Scandic’s loyalty program, Scandic Friends, were combined.
         * This means that Rica Points no longer exists and
         * that all Rica Hotels are now covered by the Scandic Friends program.
         */
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "On October 1, Rica Points and Scandic’s loyalty program, Scandic Friends, were combined")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->http->ParseForm('aspnetForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$FullRegion$RightRegion$ctl01$txtUserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$FullRegion$RightRegion$ctl01$txtPassword', $this->AccountFields['Pass']);
        $this->http->Form['ctl00$FullRegion$RightRegion$ctl01$btnLogin'] = 'Logg inn';

        return true;
    }

    public function checkErrors()
    {
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            // Server Error in '/' Application
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            // provider error
            || empty($this->http->Response['body'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are busy updating the site for you')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'ctl00\$ctl10\$ctl02')]/@href")) {
            return true;
        }

//        if ($message = $this->http->FindSingleNode("//span[contains(@id, 'ctl00_ctl09_ctl03_lblErrorMessage')][1]"))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//span[@id = 'ctl00_FullRegion_RightRegion_ctl01_lblErrorMessage']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://www.rica-hotels.com/benefits-with-rica/rica-points/my-stays/");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'ricaPoints']/h1")));
        // Balance - Rica Points
        $this->SetBalance($this->http->FindSingleNode("//h2[contains(text(), 'Rica Points')]/following-sibling::p[1]"));
        // Earned bonus nights
        $this->SetProperty("BonusNights", $this->http->FindSingleNode("//h2[contains(text(), 'Earned bonus nights')]/following-sibling::p[1]"));
        // Level
        $level = $this->http->FindSingleNode("//div[contains(@class, 'ricaPointsCard')]/@class", null, true, '/(level\d+)/ims');

        switch ($level) {
            case 'level1':
                $level = 'White';

                break;

            case 'level2':
                $level = 'Silver';

                break;

            case 'level3':
                $level = 'Gold';

                break;

            case 'level4':
                $level = 'Black';

                break;

            default:
                $this->http->Log("Status " . $level);
                $this->sendNotification("Rica Hotels - rice. Unknown Status: $level");
                $level = null;
        }
        $this->SetProperty("Level", $level);
        // Status Expiration
        $this->SetProperty("StatusExpiration", $this->http->FindSingleNode("//div[@class = 'expiration']/span[@class = 'date']"));
        // Account Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//span[@class = 'cardNumber']"));

//        // Expiration date    // refs #7372
//        $nodes = $this->http->XPath->query("//section[div[@class = 'content']]");
//        $this->http->Log("Total nodes found ".$nodes->length);
//        for ($i = 0; $i < $nodes->length; $i++) {
//            $node = $nodes->item($i);
//            $date = $this->http->FindSingleNode("div[@class = 'content']/p", $node, true, '/must be used within\s*([^<]+)/ims');
//            $date = str_replace('Desember', 'December', $date);
//            $pointsToExpire = $this->http->FindSingleNode("header/p", $node, true, '/([\d\.\,\s]+)/ims');
//            if (!isset($exp) || (strtotime($date) > time() && strtotime($date) < $exp) && $pointsToExpire>0) {
//                $this->http->Log("date ".$date);
//                $exp = strtotime($date);
//                $this->http->Log("exp ".$exp);
//                $this->SetExpirationDate($exp);
//                $this->SetProperty('PointsToExpire', $pointsToExpire);
//            }// if (!isset($exp) || strtotime($date) > $exp)
//        }// for ($i = 0; $i < $nodes->length; $i++)
    }
}
