<?php

// refs #6864

use AwardWallet\Engine\ProxyList;

class TAccountCheckerWegolo extends TAccountChecker
{
    use ProxyList;

    private $res;
    private $nodes;

    // do not redirect to "https://www.nsa.gov"
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
        $this->http->GetURL('https://www.wegolo.com/mybookings.aspx');

        if ($message = $this->http->FindPreg("/As per August 1st 2017 and onwards our website Wegolo has stopped activities\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->http->ParseForm('EAForm')) {
            return false;
        }
        $this->http->SetInputValue('tbUsername', $this->AccountFields['Login']);
        $this->http->SetInputValue('tbPasswd', $this->AccountFields['Pass']);
        $this->http->SetInputValue('btnLogin', 'Login');

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindPreg("/All information displayed here exclusively refers/")) {
            return true;
        }

        if ($message = $this->http->FindPreg("/Invalid Username.*?!/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // for itineraries
        $this->res = $this->http->FindNodes("//table[@id='tblMyBookings']/tr[@class='rslR2']/td[@class='rslA' and position() = 1]");
        $this->nodes = $this->http->FindNodes("//table[@id='tblMyBookings']/tr[@class='rslR2']/td[@class='rslA' and position() = 2]/a/@href");

        $this->http->GetURL("https://www.wegolo.com/profile.aspx");
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@name = 'tbContactFirstname']/@value") . ' ' . $this->http->FindSingleNode("//input[@name = 'tbContactLastname']/@value")));

        if (isset($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $its = [];
        $res = $this->res;
        $countRes = count($res);
        $nodes = $this->nodes;
        $this->http->Log("Total {$countRes} itneraries were found");

        for ($i = 0; $i < $countRes; $i++) {
            $it = ['Kind' => 'T'];
            $it['ReservationDate'] = strtotime($res[$i]);
            $this->http->GetURL('http://www.wegolo.com/' . $nodes[$i]);
            $it['RecordLocator'] = $this->http->FindSingleNode('//*[@id="lblBookingCodeOutbound"]', null, true, '/:\s(.*)/ims');
            $fnames = $this->http->FindNodes('//*[contains(text(), "Passenger details")]/ancestor::tr[1]/following-sibling::tr[2]/descendant::*[contains(text(), "First name")]/ancestor-or-self::td[1]/following-sibling::td[1]');
            $lnames = $this->http->FindNodes('//*[contains(text(), "Passenger details")]/ancestor::tr[1]/following-sibling::tr[2]/descendant::*[contains(text(), "Last name")]/ancestor-or-self::td[1]/following-sibling::td[1]');

            for ($i = 0; $i < count($fnames); $i++) {
                $it['Passengers'][] = $fnames[$i] . ' ' . $lnames[$i];
            }
            $charge = $this->http->FindSingleNode('//*[contains(text(), "Total Due")]/ancestor-or-self::td[1]/following-sibling::td[1]');
            preg_match('/([\d\.]+)\s(.*)/ims', $charge, $match);
            $it['TotalCharge'] = $match[1];
            $it['Currency'] = $match[2];
            $ts = [];
            $ts['FlightNumber'] = $this->http->FindSingleNode('//table[@id="tblFlightDetails"]/descendant::*[contains(text(), "Departure:")]/ancestor-or-self::td/following-sibling::td[4]', null, true, '/Flight\s(.*)/ims');
            $ts['AirlineName'] = $this->http->FindSingleNode('//table[@id="tblFlightDetails"]/descendant::*[contains(text(), "Departure:")]/ancestor-or-self::td/following-sibling::td[3]');
            $ts['Duration'] = $this->http->FindSingleNode('//table[@id="tblFlightDetails"]/descendant::*[contains(text(), "Arrival:")]/ancestor-or-self::td/following-sibling::td[4]', null, true, '/Duration:\s*(.*)/ims');
            $from = $this->http->FindSingleNode('//table[@id="tblFlightDetails"]/descendant::*[contains(text(), "Departure:")]/ancestor-or-self::td/following-sibling::td[2]');
            preg_match('/(.*)\((.*)\)/ims', $from, $match);
            $ts['DepName'] = $match[1];
            $ts['DepCode'] = $match[2];
            $ts['DepDate'] = strtotime($this->http->FindSingleNode('//table[@id="tblFlightDetails"]/descendant::*[contains(text(), "Departure:")]/ancestor-or-self::td/following-sibling::td[1]'));
            $from = $this->http->FindSingleNode('//table[@id="tblFlightDetails"]/descendant::*[contains(text(), "Arrival:")]/ancestor-or-self::td/following-sibling::td[2]');
            preg_match('/(.*)\((.*)\)/ims', $from, $match);
            $ts['ArrName'] = $match[1];
            $ts['ArrCode'] = $match[2];
            $ts['ArrDate'] = strtotime($this->http->FindSingleNode('//table[@id="tblFlightDetails"]/descendant::*[contains(text(), "Arrival:")]/ancestor-or-self::td/following-sibling::td[1]'));
            $it['TripSegments'] = [$ts];
            $its[] = $it;
        }

        return $its;
    }
}
