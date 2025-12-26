<?php

class TAccountCheckerLiveitwithcharter extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->getURL('https://www.liveitwithcharter.com/');

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('memberId', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->Form['x'] = '39';
        $this->http->Form['y'] = '17';
        $this->http->setDefaultHeader('User-Agent', 'Mozilla/5.0 (Windows; U; Windows NT 6.1; ru; rv:1.9.2.15) Gecko/20110303 Firefox/3.6.15');

        return true;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindPreg('/Due to heavy server volume we are unable to process your request at this time./ims')) {
            throw new CheckException('Live It with Charter website had a hiccup, please try to check your balance at a later time.', ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindPreg("/(We are currently performing maintainance to the Live It with Charter website\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We’re Building Something Great, Exclusively For Spectrum Customers
        if ($message = $this->http->FindPreg("/We\&rsquo;re building something great, exclusively for Spectrum customers\. Coming 2015\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // HTTP Status 404
        if ($this->http->FindPreg("/(HTTP Status 404)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode("//div[@id='copyarea']/p[@class='msg']/span", null, true, '/^([^<\.]+)/ms')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Successful access
        if ($this->http->FindNodes("//a[starts-with(@href, 'signout.jspx')]")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $tpath = '//ul[@class="account-holder"]';
        $this->http->getURL('https://www.liveitwithcharter.com/accountmang.jspx');
        //# Balance - Total points
        $points = $this->http->FindSingleNode($tpath . '/li[5]/div/div[2]/div[1]/strong[1]/span/text()');

        if (!isset($points)) {
            $points = $this->http->FindSingleNode("//strong[contains(text(), 'Total points')]/following-sibling::span[1]");
        }
        $this->SetBalance($points);
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode($tpath . '/li[1]/div/div[2]/address/strong[1]/em/text()')));
        //# Member Number
        $this->SetProperty("MemberID", $this->http->FindSingleNode('//div[@id="sidebar"]/div/div[2]/div/div/span[1]/text()', null, false, '/Member\s*Number\s*:\s*(.*)/ims'));
        //# Level
        $this->SetProperty("MemberLevel", beautifulName($this->http->FindSingleNode('//div[@id="sidebar"]/div/div[2]/div/a/text()')));
        //# Points Expiring
        $this->SetProperty("PointsExpiring", $this->http->FindSingleNode("//*[contains(text(), 'Points Expiring')]/following-sibling::span[1]", null, true, null, 0));

        //# Expiration Date  // refs #7551
        $months = [
            'January'   => 1,
            'February'  => 2,
            'March'     => 3,
            'April'     => 4,
            'May'       => 5,
            'June'      => 6,
            'July'      => 7,
            'August'    => 8,
            'September' => 9,
            'October'   => 10,
            'November'  => 11,
            'December'  => 12,
        ];

        if (isset($this->Properties['PointsExpiring']) && $this->Properties['PointsExpiring'] > 0) {
            $month = $this->http->FindSingleNode("//*[contains(text(), 'Points Expiring')]/parent::strong/following-sibling::span[1]", null, true, '/at\s*the\s*end\s*of\s*([^\)]+)/ims', 0);
            $this->http->Log("Expiration Date at the end of {$month}");

            if (isset($months[$month])) {
                $exp = mktime(0, 0, 0, $months[$month] + 1, 0, date("Y"));
                $this->http->Log("Expiration Date: " . date("M-d-Y", $exp));
                $this->SetExpirationDate($exp);
            }// if (isset($months[$month]))
        }// if (isset($this->Properties['PointsExpiring']) && $this->Properties['PointsExpiring'] > 0)
    }
}
