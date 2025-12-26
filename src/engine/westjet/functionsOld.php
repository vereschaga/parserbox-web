<?php

class TAccountCheckerWestjetOld extends TAccountChecker
{
    /**
     * Base URL.
     *
     * @var string
     */
    private $_baseUrl = 'https://www.mywestjet.com';

    /**
     * microtime, need use on login and go to profile.
     *
     * @var int
     */
    private $SWETS;

    public function LoadLoginForm()
    {
        // reset cookie
        $this->http->removeCookies();
        // set realistic user agent
        $this->http->setDefaultHeader('User-Agent', 'Mozilla/5.0 (X11; U; Linux i686; ru; rv:1.9.2.16) Gecko/20110323 Ubuntu/10.04 (lucid) Firefox/3.6.16');
        // set max redirects
        $this->http->setMaxRedirects(1);
        // load target page of login form from third level of subset frames
        $loaded = $this->loadSubFrame(
            $this->_baseUrl
            . '/eloyalty_enu/start.swe'
            . '?SWECmd=Start&SWEHo=www.mywestjet.com',
            '_sweclient._swecontent._sweview'
        );

        // check loading target page
        if (!$loaded) {
            $this->CheckErrors();

            return false;
        }
        // check parsing form
        if (!$this->http->ParseForm(null, 1)) {
            $this->CheckErrors();

            return false;
        }

        // set microtime
        $this->SWETS = intval(str_replace('.', '', microtime(true)));
        // enter the login and password
        $this->http->Form['s_9_1_7_0'] = $this->AccountFields['Login'];
        $this->http->Form['s_9_1_8_0'] = $this->AccountFields['Pass'];
        // fill other required fields of form
        $this->http->Form['SWETS'] = $this->SWETS;
        $this->http->Form['SWEField'] = 's_9_1_9_0';
        $this->http->Form['SWEMethod'] = 'AppletLogin';
        $this->http->Form['SWEBID'] = time();
        // where to send the form
        $this->http->FormURL = $this->_baseUrl . '/eloyalty_enu/start.swe#SWEApplet9';

        return true;
    }

    public function CheckErrors()
    {
        if ($message = $this->http->FindSingleNode("//body[contains(text(), 'The server you are trying to access is either busy or experiencing difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
//        ## Maintenance
//        $this->http->GetURL("https://www.mywestjet.com/eloyalty_enu/");
//        if ($message = $this->http->FindPreg("/(WestJet is hard at work making website improvements to give you the best guest experience\.)/ims"))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

        return false;
    }

    public function Login()
    {
        // form submission
        $this->http->PostForm();
        // load target page of account page from third level of subset frames
        $loaded = $this->loadSubFrame(
            $this->_baseUrl
            . '/eloyalty_enu/start.swe'
            . '?SWECmd=Login&SWEPL=1&SWETS=' . $this->SWETS,
            '_sweclient._swecontent._sweview'
        );
        // check loading target page
        if (!$loaded) {
            return false;
        }

        // login successful
        $westJetId = $this->http->FindSingleNode("//span[contains(@id, '_2_16_0')]/text()");

        if ($westJetId == $this->AccountFields['Login']) {
            return true;
        } else {
            // impossible to obtain error text
            //$message = $this->http->FindSingleNode("//input[@id='s_3_1_7_0']/../../../text()");
            throw new CheckException('Invalid login or password', ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        /*
        Warning! Tags like "@s_1_2_3_4" changing
        */
        //# Balance - Available WestJet dollars ($)
        $balance = $this->http->FindSingleNode("//span[contains(@id, '_2_19_0')]/text()");
        $this->SetBalance(0);

        if (isset($balance)) {
            $this->SetBalance($balance);
        }
        // set properties
        //# Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//span[contains(@id, '_2_18_0')]/text()"));
        //# Your current qualifying year
        $this->SetProperty("QualifyingYear", $this->http->FindSingleNode("//span[contains(@id, '_2_8_0')]/text()"));
        //# Your current qualifying spend ($)
        $qualifyingSpend = $this->http->FindSingleNode("//span[contains(@id, '_2_10_0')]/text()");

        if ($qualifyingSpend) {
            $qualifyingSpend = '$' . $qualifyingSpend;
        }
        $this->SetProperty("QualifyingSpend", $qualifyingSpend);
        //#
        $annualSpendRequired = $this->http->FindSingleNode("//span[contains(@id, '_2_9_0')]/text()");

        if ($annualSpendRequired) {
            $annualSpendRequired = '$' . $annualSpendRequired;
        }
        $this->SetProperty("AnnualSpendRequired", $annualSpendRequired);
        //# WestJet ID
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//span[contains(@id, '_2_16_0')]/text()"));

        // subaccounts
        $this->SetProperty("CombineSubAccounts", false);
        $this->http->GetURL("https://www.westjet.com/guest/en/my-westjet/my-westjet.shtml");
        $this->http->ParseForm("sign-in");
        $this->http->Form['accountID'] = $this->AccountFields['Login'];
        $this->http->Form['password'] = $this->AccountFields['Pass'];
        $this->http->PostForm();

        //# WestJet dollars
        $this->SetProperty("WestJetDollars", $this->http->FindSingleNode("//div[contains(text(), 'WestJet dollars')]/following-sibling::div[1]"));

        $nodes = $this->http->XPath->query("//div[@id='pageContent']/div[@class='box']/div[@id='tbBody']");

        if ($nodes->length > 0) {
            $this->http->Log("Find SubAccount Boxes ({$nodes->length})");
            $subAccount = [];

            for ($i = 0; $i < $nodes->length; $i++) {
                $name = $this->http->FindSingleNode("p[@class='flightInfo']", $nodes->item($i));
                $this->http->Log("Set Subaccount Name: {$name}");
                $balance = $this->http->FindSingleNode("p[@class='singleLine'][contains(text()[1],'Balance')]/text()[1]", $nodes->item($i), true, "/([\d\.]+)/ims");
                $this->http->Log("Set Subaccount Balance: {$balance}");
                $detailsFormName = $this->http->FindSingleNode("p[@class='singleLine']/a[text()='View Details']/@onclick", $nodes->item($i), true, "/forms\['([^']+)'\]/ims");
                $this->http->Log("Details Form Name: {$detailsFormName}");

                //check expiration
                $expirationDate = null;

                if (!empty($detailsFormName)) {
                    $this->http->ParseForm($detailsFormName);
                    $this->http->PostForm();
                    $link = $this->http->FindSingleNode("//a[text()='Account Statement']/@href");

                    if (!empty($link)) {
                        $this->http->GetURL("https://emergo8.sabre.com" . $link);
                        $this->http->Form = [
                            'action'          => 'false',
                            'beginDate'       => '01Sep2009',
                            'endDate'         => date("dMY"),
                            'mergedAccountId' => $this->http->FindSingleNode("//input[@name='mergedAccountId']/@value"),
                            'pageNr'          => '',
                        ];
                        $this->http->FormURL = "https://emergo8.sabre.com/tbank838/accountTransaction.do";
                        $this->http->PostForm();
                        $dates = $this->http->FindNodes("//table[@class='withBorder']/tr/td[7]");

                        foreach ($dates as $d) {
                            if (preg_match("/\d{2}\s[a-zA-Z]{3}\s\d{2}/ims", $d)) {
                                $time = strtotime($d);

                                if (!isset($expirationDate) || $time < intval($expirationDate)) {
                                    $expirationDate = $time;
                                }
                            }
                        }
                    }
                }

                $subAccount[] = [
                    "Code"           => str_replace(' ', '', $name),
                    "DisplayName"    => $name,
                    "Balance"        => floatval($balance),
                    'ExpirationDate' => $expirationDate,
                ];
            }

            if (!empty($subAccount)) {
                $this->SetProperty("SubAccounts", $subAccount);
            }
        }
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && $properties['SubAccountCode'] == 'PersonalBank') {
            return $fields['Balance'] . ' CAD';
        }

        return '$' . $fields['Balance'];
    }

    public function ParseItineraries()
    {
        $this->http->removeCookies(); // unless: session expire error

        $result = [];

        $this->http->GetURL('https://www.westjet.com/guest/en/home.shtml?tabName=managetrip-tab');

        if ($this->http->ParseForm('sign-in')) {
            $this->http->SetInputValue('accountID', $this->AccountFields['Login']);
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);

            $this->http->PostForm();

            $links = $this->http->FindNodes('//a[contains(text(), "View or Change/Refund")]/@onclick');

            foreach ($links as $link) {
                $link = preg_replace("/.*window\.open\('([^']+).+/i", '$1', $link);
                $this->http->NormalizeURL($link);
                $this->http->GetURL($link);

                $result[] = $this->ParseItinerary();

                //$url = $this->http->FindSingleNode('//a[contains(@href, "virtuallythere.com")]/@href');
                //$virtuallyThere = new TVirtuallyThereParser();
                //$its = $virtuallyThere->ParseVirtuallyIt($url);
            }
        }

        return $result;
    }

    public function ParseItinerary()
    {
        $result = [];

        $result['RecordLocator'] = $this->http->FindSingleNode('//span[@class="recordLocater"]');

        $passFirstNames = $this->http->FindNodes('//input[contains(@name, "FirstName")]/@value');
        $passLastNames = $this->http->FindNodes('//input[contains(@name, "LastName")]/@value');
        $passNames = [];

        for ($i = 0; $i < min(count($passFirstNames), count($passLastNames)); $i++) {
            $passNames[] = preg_replace('/(\w+).+/i', '$1', $passFirstNames[$i]) . ' ' . $passLastNames[$i];
        }

        if (count($passNames) > 0) {
            $result['Passengers'] = beautifulName(implode(', ', $passNames));
        }

        $result['TotalCharge'] = $this->http->FindSingleNode('//div[h3[contains(text(), "Total price")]]/p', null, true, '/([\d\.,]+)/');
        $result['Currency'] = $this->http->FindSingleNode('//div[h3[contains(text(), "Total price")]]/p', null, true, '/([A-Z]{3})/');

        $nodes = $this->http->XPath->query('//div[@class="flightOptions"]/div[contains(@class, "flight") and div[contains(@class, "column")]]');
        $this->http->Log("Found {$nodes->length} segments");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $segment = [];

            $segment['DepCode'] = $this->http->FindSingleNode('*//p[@class="departure"]/text()[2]', $node, true, '/\(([A-Z]{3})\)/');
            $segment['DepName'] = preg_replace('/\s+,/', ',', $this->http->FindSingleNode('*//p[@class="departure"]', $node, true, '/(.+)\([A-Z]{3}\)/'));
            $date = null;

            if ($depDate = $this->http->FindSingleNode('(*//p[@class="departure"]/preceding-sibling::p[@class="date"])[1]', $node)) {
                $date = strtotime($depDate);
            }

            if ($date && $depTime = $this->http->FindSingleNode('(*//p[contains(text(), "Depart:")]/following-sibling::p[@class="time"])[1]', $node)) {
                $date = strtotime($depTime, $date);
            }
            $segment['DepDate'] = $date;

            $segment['ArrCode'] = $this->http->FindSingleNode('*//p[@class="arrival"]/text()[2]', $node, true, '/\(([A-Z]{3})\)/');
            $segment['ArrName'] = preg_replace('/\s+,/', ',', $this->http->FindSingleNode('*//p[@class="arrival"]', $node, true, '/(.+)\([A-Z]{3}\)/'));
            $date = null;

            if ($depDate = $this->http->FindSingleNode('(*//p[@class="arrival"]/preceding-sibling::p[@class="date"])[1]', $node)) {
                $date = strtotime($depDate);
            }

            if ($date && $depTime = $this->http->FindSingleNode('(*//p[contains(text(), "Arrive:")]/following-sibling::p[@class="time"])[1]', $node)) {
                $date = strtotime($depTime, $date);
            }
            $segment['ArrDate'] = $date;

            $segment['FlightNumber'] = $this->http->FindSingleNode('*//p[@class="flightNumber"]', $node, true, '/(\w+\s*\d+)/ims');
            $segment['AirlineName'] = $this->http->FindSingleNode('*//p[@class="airline"]', $node);
            $segment['Aircraft'] = $this->http->FindSingleNode('*//p[@class="aircraft"]/text()[2]', $node);
            $segment['Cabin'] = $this->http->FindSingleNode('*//p[@class="aircraft"]/span', $node, true, '/Cabin:\s+(\w+)/ims');
            $segment['Seats'] = preg_replace('/\s+,/', ',', $this->http->FindSingleNode('*//p[contains(text(), "Seat(s):")]', $node, true, '/Seat\(s\)\:\s+(.+)/ims'));

            $result['TripSegments'][] = $segment;
        }

        return $result;
    }

    /**
     * Parsing frames on page.
     *
     * @param string $url
     *
     * @return array
     */
    private function parseFrames($url)
    {
        /**
         * Using Regular Expression for search frames,
         * because XPath can't find.
         */

        // go to page with frames
        $this->http->getURL($url);

        // get HTML
        $body = $this->http->Response['body'];

        // search frames with names and urls in HTML
        preg_match_all('/<frame[^>]*name="([^"]*)"[^>]*src="([^"]*)"[^>]*>/', $body, $mtchs);

        /**
         * array of found frames
         * name => url.
         */
        $frames = [];

        /**
         * fill array with found frames.
         */
        foreach ($mtchs[1] as $key => $name) {
            $frames[$name] = $this->_baseUrl . $mtchs[2][$key];
        }

        return $frames;
    }

    /**
     * Loading page from subset frames.
     *
     * @param string $url - starting url
     * @param string $path - path to page in subset frames by names,
     * delimiter is dot, example: frame1.subframe2.subframe3
     *
     * @return bool - true if target page loaded
     */
    private function loadSubFrame($url, $path)
    {
        // check input parameters
        if (strlen(trim($url)) == 0 || strlen(trim($path)) == 0) {
            return false;
        }

        // get array of names frames
        $frNames = explode('.', $path);

        // set starting url
        $frsUrl = $url;

        // iterate all names and load all the frames one by one
        foreach ($frNames as $name) {
            // load next frame if url is not empty
            if (strlen(trim($frsUrl)) > 0) {
                $frames = $this->parseFrames($frsUrl);
            } else {
                return false;
            }

            // set url to next frame if frame is exist
            if (array_key_exists($name, $frames)) {
                $frsUrl = $frames[$name];
            } else {
                return false;
            }
        }

        // load target page if the URL is not empty
        if (strlen(trim($frsUrl)) > 0) {
            $this->http->getURL($frsUrl);

            return true;
        } else {
            return false;
        }
    }
}
