<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerIsrael extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    /**
     * Contains session id of the target site.
     *
     * @var string
     */
    private $sessionID;

    private $bookingClasses;
    private $route;

    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerIsraelSelenium.php";

            return new TAccountCheckerIsraelSelenium();
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
        //$this->setProxyBrightData(null, "us_residential", "us", true);
    }

    /**
     * Parse table cell value function.
     *
     * @return void
     */
    public function checkAndSetPropertyValue($propertyName, $propertyFilterText)
    {
        $propertyValue = $this->http->FindSingleNode('//td[contains(text(),"' . $propertyFilterText . '")]/ancestor::tr[1]/td[2]');

        if (isset($propertyValue)) {
            $this->SetProperty($propertyName, $propertyValue);
        }
    }

    /**
     * Parse table cell bold value function.
     *
     * @return void
     */
    public function checkAndSetStatusValue($propertyName, $propertyFilterText)
    {
        $propertyValue = $this->http->FindSingleNode('//td/descendant-or-self::*[contains(text(),"' . $propertyFilterText . '")]/ancestor::tr[1]/td[2]');

        if (isset($propertyValue)) {
            $this->SetProperty($propertyName, $propertyValue);
        }
    }

    /**
     * Try to reformat date from %d/%m/%Y, to %m/%d/%Y
     * if failed returns $dateStr.
     *
     * @param $dateStr String
     *
     * @return string
     */
    public function reformatDate($dateStr)
    {
        //if date in format DD/MM/YYYY, then need to exchange DD and MM
        $dateAssoc = strptime($dateStr, '%d/%m/%Y');

        if ($dateAssoc !== false) {
            return ($dateAssoc['tm_mon'] + 1) . '/' . $dateAssoc['tm_mday'] . '/' . ($dateAssoc['tm_year'] + 1900);
        }

        return $dateStr;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->LogHeaders = true;
        $this->http->GetURL('https://www.elal-matmid.com/en/Login/Pages/Login.aspx');

        $rbzreqid = $this->http->FindPreg("/bzns.rbzreqid=\"([^\"]+)/");
        $this->http->setCookie("rbzreqid", $rbzreqid, "www.elal-matmid.com");
        $this->http->setCookie("WSS_FullScreenMode", "false", "www.elal-matmid.com");
//        $this->http->setCookie("ClubGlobalCookie", "false", "/en/Login/Pages/");
        sleep(2);

        $this->http->GetURL('https://www.elal-matmid.com/en/Login/Pages/Login.aspx');

        if (!$this->http->ParseForm("aspnetForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue($this->http->FindSingleNode("//input[contains(@id, 'MembertxtID')]/@name"), preg_replace('/\D/', '', $this->AccountFields['Login']));
        $this->http->SetInputValue($this->http->FindSingleNode("//input[contains(@id, 'PasswordtxtID')]/@name"), $this->AccountFields['Pass']);
//        $this->http->Form['__EVENTTARGET'] = 'ctl00$ContentPlaceHolder1$FrqFlyerSignIn1$lnkSignIn';

        return true;
    }

    public function checkErrors()
    {
        // "Due to maintenance, EL AL's site is not available at the moment."
        if ($message = $this->http->FindSingleNode('//p[contains(text(),"site is not available")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(Due to maintenance, EL AL's site is not available at the moment.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Connect to upstream server timed out
        if ($message = $this->http->FindSingleNode('//h1[contains(text(),"server timedout")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(connect to upstream server timed)/ims")) {
            throw new CheckException("Connect to upstream server timed out", ACCOUNT_PROVIDER_ERROR);
        }

        // Site is not available
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "site is not available")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Upstream write/read error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'upstream write/read error')]")) {
            throw new CheckException("The site is temporarily unavailable", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        //# General error
        if ($message = $this->http->FindPreg("/(General error)/ims")) {
            throw new CheckException("The site is temporarily unavailable. PLease try again later", ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request.
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error in '/ELAL' Application.
        if ($message = $this->http->FindPreg("/(Server Error in \'\/ELAL\' Application\.)/ims")) {
            throw new CheckException("The website is experiencing technical difficulties, please try to check your balance at a later time.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/

        // Retry login
        if ($this->http->FindSingleNode("//u[contains(text(), 'Request is not allowed.')]") && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            throw new CheckRetryNeededException(3, 10);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is allowed
        if ($this->http->FindSingleNode("//span[contains(text(), 'Log-Out')]")) {
            return true;
        }

        //# Member block
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Member block')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        //# Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[contains(@id, 'errorMessage')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        //# Update Password
        if ($message = $this->http->FindSingleNode("//div[contains(@id, 'UserControlTd')]/h3[contains(text(), 'Update Password')]")) {
            throw new CheckException("Please update your password", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Member Number
        $memberNo = $this->http->FindSingleNode("//span[contains(@id, 'PersonalDetails_lblIDNumber')]");
        //# Status
        $clubStatus = $this->http->FindSingleNode("//span[contains(@id, 'PersonalDetails_lblCurrentClubDesc')]");
        //# Get page "Account Balance"
        $appname = $this->http->FindSingleNode("//span[contains(text(), 'Account Balance')]//parent::a/@onclick", null, true, "/Appname\'\,\'value\'\:\'([^\']+)/ims");
        $prgname = $this->http->FindSingleNode("//span[contains(text(), 'Account Balance')]//parent::a/@onclick", null, true, "/Prgname\'\,\'value\'\:\'([^\']+)/ims");
        $this->sessionID = $this->http->FindSingleNode("//span[contains(text(), 'Account Balance')]//parent::a/@onclick", null, true, "/sessionid\'\,\'value\'\:\'([^\']+)/ims");
        $lang = $this->http->FindSingleNode("//span[contains(text(), 'Account Balance')]//parent::a/@onclick", null, true, "/Lang\'\,\'value\'\:\'([^\']+)/ims");
        $arguments = $this->http->FindSingleNode("//span[contains(text(), 'Account Balance')]//parent::a/@onclick", null, true, "/Arguments\'\,\'value\'\:\'([^\']+)/ims");
        $innerURL = $this->http->FindSingleNode("//span[contains(text(), 'Account Balance')]//parent::a/@onclick", null, true, "/InnerURL\'\,\'value\'\:\'([^\']+)/ims");

//        $this->http->GetURL("https://app.elal.co.il/ClubAirSite/WebForms/container.aspx?Appname=".$appname."&Prgname=".$prgname."&sessionid=".$sessionid."&Lang=".$lang."&Arguments=".$arguments."&InnerURL=".$innerURL);
        $this->http->GetURL("https://app.elal.co.il/Magic94Scripts/Mgrqispi94.dll?Appname=" . $appname . "&Prgname=" . $prgname . "&sessionid=" . $this->sessionID . "&Lang=" . $lang . "&Arguments=" . $arguments . "&InnerURL=" . $innerURL);

        //# Balance - Total Points
        $this->SetBalance($this->http->FindSingleNode('//td/descendant-or-self::*[contains(text(),"Total Points:")]/ancestor::tr[1]/td[2]'));

        //Main properties
        //# Member Number
        if (!isset($memberNo)) {
            $memberNo = $this->http->FindSingleNode('//td/descendant-or-self::*[contains(text(), "Member no")]', null, false, '/Member no.*:\s*(.*)/i');
        }
        $this->SetProperty('MemberNo', $memberNo);
        //# Name
        $name = $this->http->FindSingleNode('//td/descendant-or-self::*[contains(text(), "Dear")]', null, false, '/Dear\s*(.*)/i');

        if (isset($name)) {
            $this->SetProperty('Name', beautifulName($name));
        }

        if (!isset($clubStatus)) {
            $clubStatus = $this->http->FindSingleNode('//td[contains(text(),"Club:")]', null, false, '/Club:\s*([^ ]*)/i');
        }

        if (!isset($clubStatus)) {
            $clubStatus = $this->http->FindSingleNode('//td/descendant-or-self::*[contains(text(), "Your current membership period")]', null, false, '/current\s*membership\s*period\s*in\s*(.*)\s*ends/i');
        }

        if (isset($clubStatus)) {
            $this->SetProperty('CurrentClubStatus', trim($clubStatus));
        }

        //Balance properties
        $this->checkAndSetPropertyValue('BasicPoints', 'Basic Points');
        $this->checkAndSetPropertyValue('ExtraPoints', 'Extra Points:');
        $this->checkAndSetPropertyValue('PartnerPoints', 'Partner Points:');
        $this->checkAndSetPropertyValue('OverdraftedPoints', 'Overdrafted Points:');

        //Additional status properties
        $membershipStatusEndDate = $this->http->FindSingleNode('//td/descendant-or-self::*[contains(text(), "Your current membership period")]', null, false, '/(\d+[\s\/]+\w+[\s\/]+\d+)/i');

        if (isset($membershipStatusEndDate)) {
            $this->SetProperty('MembershipStatusEndDate', $membershipStatusEndDate);
        }

        $this->checkAndSetStatusValue('RemainStatusPoints', 'points required to remain');
        $this->SetProperty('NextClubStatus', $this->http->FindSingleNode('//td/descendant-or-self::*[contains(text(),"points required to qualify")]', null, false, '/points\s*required\s*to\s*qualify\s*for\s*(.*):/i'));
        $this->checkAndSetStatusValue('QualifyNextStatusPoints', 'points required to qualify');

        $earliestExpirationDate = false;
        $expirationPoints = false;
        //Expiration date (parsing)
        $nodes = $this->http->XPath->query('//td/descendant::*[contains(text(), "Points to redeem during the next")]/ancestor::tr[1]/following::tr[count(td) > 9]');

        if ($nodes) {
            $expirationColumnIndex = false;
            $pointsColumnIndex = false;
            //Find date expiration and expiration points column index
            for ($rowIndex = 0; $rowIndex < $nodes->length; $rowIndex++) {
                //Check that we can use this row node
                if ($nodes->item($rowIndex)->nodeType == XML_ELEMENT_NODE && $nodes->item($rowIndex)->hasChildNodes()) {
                    $childNodes = $nodes->item($rowIndex)->childNodes;
                    //Check indexes
                    if ($expirationColumnIndex !== false && $pointsColumnIndex !== false) {
                        //First date - will be earliest
                        if ($earliestExpirationDate === false) {
                            $earliestExpirationDate = strtotime($this->reformatDate(CleanXMLValue($childNodes->item($expirationColumnIndex)->nodeValue)));
                        } else {
                            $date = strtotime($this->reformatDate(CleanXMLValue($childNodes->item($expirationColumnIndex)->nodeValue)));
                            //if date changes, then break the cicle
                            if ($date === false || $date != $earliestExpirationDate) {
                                break;
                            }
                        }
                        //Aggregate points
                        if ($expirationPoints === false) {
                            $expirationPoints = intval(CleanXMLValue($childNodes->item($pointsColumnIndex)->nodeValue));
                        } else {
                            $expirationPoints += intval(CleanXMLValue($childNodes->item($pointsColumnIndex)->nodeValue));
                        }
                    } else {
                        //if can't find date and points indexes in ten row, then out of cicle
                        //i think indexes will be not found, save response time
                        if ($rowIndex > 10) {
                            break;
                        }
                        //Find date and points indexes, check every cell in row
                        for ($cellIndex = 0; $cellIndex < $childNodes->length; $cellIndex++) {
                            if ($childNodes->item($cellIndex)->nodeType == XML_ELEMENT_NODE) {
                                if (preg_match('/Expiration/i', CleanXMLValue($childNodes->item($cellIndex)->nodeValue))) {
                                    $expirationColumnIndex = $cellIndex;
                                }

                                if (preg_match('/Points/i', CleanXMLValue($childNodes->item($cellIndex)->nodeValue))) {
                                    $pointsColumnIndex = $cellIndex;
                                }
                            }

                            if ($expirationColumnIndex !== false && $pointsColumnIndex !== false) {
                                break;
                            }
                        }
                        //Cell cicle ends
                    }
                    //Check that found indexes IF ELSE ends
                }
                //end of if $childNode->nodeType == XML_ELEMENT_NODE && childNode->hasChildNodes()
            }
            //Row cicle ends
        }
        //end if($nodes)

        //Set expiration date if finded
        if ($earliestExpirationDate !== false) {
            $this->SetExpirationDate($earliestExpirationDate);
        }
        //Set expiration points if finded
        if ($expirationPoints !== false) {
            $this->SetProperty('ExpirationPoints', $expirationPoints);
        }
        //this is the end of Parse function
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.elal.co.il/ELAL/English/States/General/";
        //		$arg["SuccessURL"] = "http://www.elal.co.il/ELAL/English/MatmidFrequentFlyer/MyAccount";
        return $arg;
    }

    // refs #4204
    public function ParseItineraries()
    {
        $this->http->GetURL("https://app.elal.co.il/Magic94Scripts/Mgrqispi94.dll?Appname=ClubAirCtl&Prgname=RESERVATIONS&sessionid=" . $this->sessionID . "&Lang=EN&Arguments=Lang%2Csessionid&InnerURL=https%25253a%25252f%25252fapp.elal.co.il%25252fMagic94Scripts%25252fMgrqispi94.dll");

        if ($noItin = $this->http->FindPreg("/(No reservations were found\.)/ims")) {
            return $this->noItinerariesArr();
        }

        $xpath = $this->http->XPath;
        $links = $xpath->query("//table/tbody/tr/td/a");

        if (isset($links)) {
            $this->logger->debug("Found {$links->length} items");
        }
        $this->bookingClasses = $this->http->FindNodes("//table/tbody/tr/td[5]");
        $this->route = $this->http->FindNodes("//table/tbody/tr/td[4]");
        $pattern_url = $this->http->FindPreg('/else\s*\{\s*window.open\(\"([^\,]+)/');

        if (!$pattern_url) {
            $pattern_url = $this->http->FindPreg('/window\.open\(\"(https:\/\/booking.elal.co.il\/newBooking\/changeOrder\.do[^\,]+)\"\,/');
        }
        //$this->http->Log("URL - ".var_export($pattern_url, true), true);
        $flightNumber = $xpath->query("//table/tbody/tr/td[3]");
        $result = [];

        for ($i = 0; $i < $links->length; $i++) {
            if (preg_match("/\'([^\']+)\'\)/", $links->item($i)->getAttribute('href'), $number)) {
                //$this->http->Log("PNR Code - ".var_export($number[1], true), true);
                //$url = str_replace('"+PNR+"', $number[1], $pattern_url);
                $url = preg_replace("/\"\s+\+\s+PNR\s+\+\s+\"/", $number[1], $pattern_url);
                //$this->http->Log("Replace - ".var_export($url, true), true);

                //# exclude clones ($flightNumber)
                if (isset($num) && $num == $number[1]) {
                    continue;
                }

                $result[] = $this->ParseItinerary($url);
                $num = $number[1];
            }
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Booking code (6 characters)",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Last name",
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://booking.elal.com/manage/login?lang=en&LANG=EN";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->setProxyGoProxies(null, 'es');

        return $this->CheckConfirmationNumberInternalSelenium($arFields, $it);
        // return $this->CheckConfirmationNumberInternalCurl($arFields, $it);
    }

    /*
    private function CheckConfirmationNumberInternalCurl($arFields, &$it)
    {
        $this->http->GetURL('https://booking.elal.co.il/newBooking/JavaScriptServlet');
        $csrf = $this->http->FindPreg("/\"OWASP_CSRFTOKEN\", \"([^\"]+)/");
        $this->http->GetURL($this->ConfirmationNumberURL([]));

        if (!$this->http->ParseForm("changeOrder") || !$csrf) {
            return $this->notifications($arFields);
        }
        $this->http->FormURL .= "?OWASP_CSRFTOKEN={$csrf}";
        $this->http->SetInputValue('DIRECT_RETRIEVE_LASTNAME', $arFields["LastName"]);
        $this->http->SetInputValue('REC_LOC', $arFields["ConfNo"]);
        $this->http->SetInputValue('LANG', 'EN');
        $this->http->SetInputValue('LANGUAGE', 'EN');
        $this->http->SetInputValue('LOGIN_PAGE', 'TRUE');
        $this->http->SetInputValue('RESSYSTEMID', '');
        $this->http->SetInputValue('SYSTEMID', '5');
        $this->http->SetInputValue('OWASP_CSRFTOKEN', $csrf);

        if (!$this->http->PostForm()) {
            return $this->notifications($arFields);
        }

        $enc = $this->http->FindSingleNode("//input[@id = 'ENC']/@value");
        $pspurl = $this->http->FindSingleNode("//input[@id = 'SO_SITE_EXT_PSPURL']/@value");
        $site = $this->http->FindSingleNode("//input[@id = 'SITE']/@value");

        if ($error = $this->http->FindSingleNode("//label[contains(text(), 'Sorry, We could not find your reservation details.')]")) {
            return $error;
        }

        if ($this->http->ParseForm()) {
            $this->http->Form = [];
            $fields = [
                'amadeusURL'                   => '',
                'EMBEDDED_TRANSACTION'         => 'RetrievePNR',
                'ENC'                          => $enc,
                'ENCT'                         => '1',
                'envRadio'                     => 'Site Acceptance',
                'EXTERNAL_ID'                  => 'RetrievePNR|referer:https://booking.elal.co.il/newBooking/changeOrderNewSite.jsp||EUROPE| (systemId=5 )',
                'LANGUAGE'                     => 'GB',
                'SERVICING_TYPE'               => 'BOTH',
                'SITE'                         => $site,
                'SO_SITE_ALLOW_SPECIAL_MEAL'   => 'TRUE',
                'SO_SITE_BOOL_DISPLAY_ETKT'    => 'TRUE',
                'SO_SITE_CSSR_CATALOG'         => 'DYNAMIC',
                'SO_SITE_DISP_ONHOLD_ALL_MOP'  => 'TRUE',
                'SO_SITE_DISPLAY_CSSR_DOC_NUM' => 'TRUE',
                'SO_SITE_EXT_PSPURL'           => $pspurl,
                'SO_SITE_MODIFY_OUTSIDE_PNR'   => 'TRUE',
                'SO_SITE_MOP_CALL_ME'          => 'FALSE',
                'SO_SITE_OFFICE_ID'            => 'TLVLY08AA',
                'SO_SITE_RT_DEFAULT_MOD_ETKT'  => 'TRUE',
                'SO_SITE_RT_PNR_FROM_OUTSIDE'  => 'TRUE',
                'SO_SITE_SEATMAP_ORIENTATION'  => 'VERTICAL',
                'SO_SITE_SPEC_SERV_CHARGEABLE' => 'TRUE',
                'SO_SITE_SRV_CMD_FORMAT_MODE'  => 'LEGACY',
                'SO_SITE_USE_PENDING_TRIPS'    => 'TRUE',
            ];

            foreach ($fields as $key => $value) {
                $this->http->SetInputValue($key, $value);
            }

            if (!$this->http->PostForm()) {
                return $this->notifications($arFields);
            }
        }

        $it = $this->ParseItinerary();

        return null;
    }
    */

    private function ParseItinerary($url = "")
    {
        $this->logger->notice(__METHOD__);

        if ($url != "") {
            $this->http->GetURL($url);
        }

        if ($this->http->XPath->query("//input[@name='SO_SITE_DISP_ONHOLD_ALL_MOP']")->length > 0) {
            if (!$this->http->ParseForm()) {
                return [];
            }
            $this->http->PostForm();
        }
        $result = ["Kind" => "T"];
        $xpath = $this->http->XPath;
        // RecordLocator
        $result['RecordLocator'] = $this->http->FindSingleNode("//input[@id = 'pnrNbr']/@value");
        //# Status
        $result['Status'] = $this->http->FindSingleNode("//td[contains(text(), 'Trip status')]/span");
        // Passengers
        $passengers = $this->http->FindNodes("//tr[td[contains(text(), 'Frequent flyer') or contains(text(), 'Known Traveller Number')]]/ancestor::tr[1]/preceding-sibling::tr//span[contains(@class, 'textBold')]");

        if (count($passengers) == 0) {
            $passengers = $this->http->FindNodes('//th[contains(@id, "documentNumber_")]/following-sibling::td[1]');
        }

        if (count($passengers) > 0) {
            $result['Passengers'] = array_values(array_unique($passengers));
        }
        // AccountNumber
        $accounts = $this->http->FindNodes("//td[contains(text(), 'Frequent flyer') or contains(text(), 'Known Traveller Number')]/following-sibling::td");

        if (isset($accounts[0])) {
            $result['AccountNumbers'] = array_unique($accounts);
        }

        // Segments
        $nodes = $xpath->query("//input[contains(@id, 'flightNumber')]/@value");
        $this->logger->debug("Total {$nodes->length} segments were found");
        $segments = [];

        $i = 0;
        $k = 0;
        $specialRequests = $this->http->XPath->query("//div[@id = 'sh_specialReqTripSum']//table/tbody/tr[not(@class = 'boundTitle')]");
        $this->logger->debug("Total {$specialRequests->length} special requests were found");

        while ($i < $nodes->length) {
            //# FlightNumber
            $segments[$i]['FlightNumber'] = $this->http->FindSingleNode("//input[contains(@id, 'flightNumber')]/@value", null, true, null, $i);
            // AirlineName
            $segments[$i]['AirlineName'] = $this->http->FindSingleNode("//input[contains(@id, 'airlineCode')]/@value", null, true, null, $i);

            if (!$segments[$i]['AirlineName']) {
                $segments[$i]['AirlineName'] = $this->http->FindSingleNode("//input[contains(@id, 'airlineName')]/@value", null, true, null, $i);
            }
            //# DepCode
            $segments[$i]['DepCode'] = $this->http->FindSingleNode("//input[contains(@id, 'departureCode')]/@value", null, true, null, $i);
            //# DepName
            $segments[$i]['DepName'] = $this->http->FindSingleNode("//input[contains(@id, 'departureCity_')]/@value", null, true, null, $i);
            //# DepartureTerminal
            $terminal1 = $this->http->FindSingleNode(sprintf('(//table[contains(@id, "tabFgtReview")])[%s]//span[contains(text(), "Departure:")]/ancestor::td[1]/following-sibling::td[2]', $i + 1), null, true, '/terminal\s*(\w+)/i');
            $segments[$i]['DepartureTerminal'] = $terminal1;
            //# DepDate
            $segments[$i]['DepDate'] = strtotime($this->http->FindSingleNode("//input[contains(@id, 'departureDate')]/@value", null, true, null, $i));
            //# ArrCode
            $segments[$i]['ArrCode'] = $this->http->FindSingleNode("//input[contains(@id, 'arrivalCode')]/@value", null, true, null, $i);
            //# ArrName
            $segments[$i]['ArrName'] = $this->http->FindSingleNode("//input[contains(@id, 'arrivalCity_')]/@value", null, true, null, $i);
            //# ArrivalTerminal
            $terminal2 = $this->http->FindSingleNode(sprintf('(//table[contains(@id, "tabFgtReview")])[%s]//span[contains(text(), "Arrival:")]/ancestor::td[1]/following-sibling::td[2]', $i + 1), null, true, '/terminal\s*(\w+)/i');
            $segments[$i]['ArrivalTerminal'] = $terminal2;
            //# ArrDate
            $segments[$i]['ArrDate'] = strtotime($this->http->FindSingleNode("//input[contains(@id, 'arrivalDate')]/@value", null, true, null, $i));

            /*
            ## Meal
            $nealPreference = $this->http->FindSingleNode('//td[contains(@id, "segMeal_{'.$k.'}_0") and not(contains(@id, "Etkt"))]', null, true, null, $k);
            if (isset($nealPreference) && trim($nealPreference) != 'No special meal' && !empty($nealPreference)) {
                $segments[$i]['Meal'] .= $nealPreference.', ';
            }

            $segments[$i]['Seats'] = '';
            $seatNumber = $this->http->FindSingleNode("td[2]", $specialRequests->item($k));
            do {
                ## Seats
                if (isset($seatNumber) && !empty($seatNumber)) {
                    $segments[$i]['Seats'] .= $seatNumber.', ';
                }
                $k++;
                $nextSegment = $this->http->FindSingleNode("th", $specialRequests->item($k));
                $seatNumber = $this->http->FindSingleNode("td[2]", $specialRequests->item($k));
            } while (!$nextSegment && $k < $specialRequests->length);
            */

            // for CheckConfirmationNumberInternal
            $seats = [];

            if (!empty($result['Passengers']) && empty($segments[$i]['Seats'])) {
                foreach ($result['Passengers'] as $passenger) {
                    // Seats
                    $passenger = preg_replace("/^(?:Mrs|Ms|Dr|Mr)\s*/", "", $passenger);
                    $seat = trim($this->http->FindSingleNode("//h3[contains(text(), '{$passenger}')]/following-sibling::ul[1]//table//tr[not(@class = 'boundTitle')]/td[2]"));

                    if ($seat) {
                        $segments[$i]['Seats'][] = $seat;
                    }
                }// foreach ($result['Passengers'] as $passenger)
            }

            //# Duration
            $segments[$i]['Duration'] = $this->http->FindSingleNode("//*[contains(@id, 'segDuration')]", null, true, null, $i);
            //# Aircraft
            $segments[$i]['Aircraft'] = $this->http->FindSingleNode("//*[contains(@id, 'segAircraft')]", null, true, null, $i);
            //# Cabin
            $segments[$i]['Cabin'] = $this->http->FindSingleNode("//tr[@id='faretype']/td[2]", null, true, null, $i);

            if (isset($this->bookingClasses[0]) && trim($segments[$i]['Seats']) != 'Unspecified') {
                $segments[$i]['BookingClass'] = $this->bookingClasses[0];
            }

            if (isset($this->route[0]) && strstr($this->route[0], '-' . substr($segments[$i]['ArrName'], 0, 7))) {
                array_splice($this->bookingClasses, 0, 1);
                array_splice($this->route, 0, 1);
            }

            $i++;
        }

        $result['TripSegments'] = $segments;

        return $result;
    }

    private function ParseItineraryNew(TAccountCheckerIsrael $selenium): array
    {
        $this->logger->notice(__METHOD__);

        $result = ["Kind" => "T"];
        // RecordLocator
        $result['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Booking code:')]",
            null, false, "/:\s+(\w+)$/");

        // Passengers
        $passengers = $this->http->FindNodes("//h4[contains(@class,'ui-avatar__title')]");

        if (count($passengers) > 0) {
            $result['Passengers'] = array_values(array_unique($passengers));
        }
        // AccountNumber
        $accounts = array_values(array_filter(array_unique($this->http->FindNodes("//input[contains(@id,'matMidNumber')]"))));

        if (isset($accounts[0]) && !empty($accounts[0])) {
            $result['AccountNumbers'] = $accounts;
        }
        // Seats
        $routes = $this->http->FindNodes("//div[normalize-space()='Seating']/following-sibling::*[1]//div[contains(@class,'selection-summary__segment-title')]");
        $places = $this->http->FindNodes("//div[normalize-space()='Seating']/following-sibling::*[1]//div[contains(@class,'selection-summary__content')]");

        if (count($routes) !== count($places)) {
            $this->sendNotification("check seats // MI");
        } else {
            $seats = array_combine($routes, $places);
            $this->logger->debug(var_export($seats, true));
        }

        $segmentsCount = $this->http->XPath->query("//a[contains(.,'Flight details')]")->length;
        $this->logger->debug("Found $segmentsCount segments");

        if ($segmentsCount > 0) {
            for ($i = 1; $i <= $segmentsCount; $i++) {
                $seg = [];
                $segRoots = $this->http->XPath->query("(//a[contains(.,'Flight details')])[{$i}]/ancestor::div[contains(@class,'ui-bound__info')][1]");

                if ($segRoots->length !== 1) {
                    $this->logger->error("can't find bound info block");
                    $result['TripSegments'][] = $seg;

                    return $result;
                }
                $segRoot = $segRoots->item(0);
                $seg['FlightNumber'] = $this->http->FindSingleNode("./following-sibling::div[1]", $segRoot, false,
                    "/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\b/");
                $seg['AirlineName'] = $this->http->FindSingleNode("./following-sibling::div[1]", $segRoot, false,
                    "/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+\b/");
                $seg['Operator'] = $this->http->FindSingleNode("./following-sibling::div[1]", $segRoot, false,
                    "/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+\s+Operated by\s+(.+)/");

                $duration = $this->http->FindSingleNode("./descendant::div[contains(@class,'ui-bound__duration__line')]/preceding::text()[normalize-space()!=''][1]",
                    $segRoot);

                if (!$detail = $selenium->waitForElement(WebDriverBy::xpath("(//a[contains(.,'Flight details')])[{$i}]"),
                    0)
                ) {
                    continue;
                }
                $detail->click();
                $close = $selenium->waitForElement(WebDriverBy::xpath("//popin-root//div[contains(@class,'popin-root__close')]"), 7);

                if (!$close) {
                    $close = $selenium->waitForElement(WebDriverBy::xpath("//popin-root//div[contains(@class,'popin-root__close')]"), 3);

                    if (!$close) {
                        $result['TripSegments'][] = $seg;
                        $this->savePageToLogs($selenium);
                        $this->logger->error("error popin");

                        return $result;
                    }
                }
                $this->savePageToLogs($selenium);

                if (empty($this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]"))) {
                    $result['TripSegments'][] = $seg;
                    $this->logger->error("check parse");

                    return $result;
                }
                $points = $this->http->FindNodes("//div[contains(@class,'popin-bound-detail__flight-infos')]/ancestor::div[@class='popin-bound-detail__flight']/preceding-sibling::div[1]//div[@class='popin-bound-detail__loc']/descendant::text()[1]");

                if (count($points) === 2) {
                    $route = implode(' - ', $points);
                    $this->logger->debug("route: " . $route);

                    if (isset($seats[$route])) {
                        $seg['Seats'] = join(', ', explode(' ', $seats[$route]));
                    }
                }
                $depDate = $this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]/div[1]/div[contains(@class,'date')]");
                $this->logger->debug("DepDate: {$depDate}");
                $depDate = strtotime(str_replace(', ', ' ', $depDate));
                $arrDate = $this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]/div[2]/div[contains(@class,'date')]");
                $this->logger->debug("ArrDate: {$arrDate}");
                $arrDate = strtotime(str_replace(', ', ' ', $arrDate));
                $seg['DepName'] = $this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]/div[1]/div[contains(@class,'airport')]",
                    null, false, "/(.+)\s*\-\s*[A-Z]{3}\s*$/");
                $seg['ArrName'] = $this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]/div[2]/div[contains(@class,'airport')]",
                    null, false, "/(.+)\s*\-\s*[A-Z]{3}\s*$/");
                $seg['DepCode'] = $this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]/div[1]/div[contains(@class,'airport')]",
                    null, false, "/\s*\-\s*([A-Z]{3})\s*$/");
                $seg['ArrCode'] = $this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]/div[2]/div[contains(@class,'airport')]",
                    null, false, "/\s*\-\s*([A-Z]{3})\s*$/");
                $seg['DepDate'] = strtotime($this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]/div[1]//p[starts-with(normalize-space(),'Departure time')]/descendant::text()[normalize-space()!=''][2]"),
                    $depDate);
                $seg['ArrDate'] = strtotime($this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]/div[2]//p[starts-with(normalize-space(),'Arrival time')]/descendant::text()[normalize-space()!=''][2]"),
                    $arrDate);
                $seg['DepartureTerminal'] = $this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]/div[1]//p[starts-with(normalize-space(),'Terminal')]/descendant::text()[normalize-space()!=''][2]");
                $seg['ArrivalTerminal'] = $this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]/div[2]//p[starts-with(normalize-space(),'Terminal')]/descendant::text()[normalize-space()!=''][2]");
                //$seg['Aircraft'] = $this->http->FindSingleNode("//div[contains(@class,'popin-bound-detail__flight-infos')]/div//p[starts-with(normalize-space(),'Aircraft')]/descendant::text()[normalize-space()!=''][2]");
                $close->click();
                $this->savePageToLogs($selenium);

                if ($segmentsCount === 1) {
                    $seg['Duration'] = $duration;
                }
                $result['TripSegments'][] = $seg;
            }
        }

        return $result;
    }

    private function notifications($arFields)
    {
        $this->sendNotification("failed to retrieve itinerary by conf #");

        return null;
    }

    private function CheckConfirmationNumberInternalSelenium($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        /** @var TAccountCheckerIsrael $selenium */
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("running selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            //$selenium->useChromePuppeteer();
            $this->keepCookies(false);
            $resolutions = [
                [1280, 720],
                [1280, 768],
                //                [1280, 800],
                //                [1360, 768],
                //                [1366, 768],
            ];
            $selenium->setScreenResolution($resolutions[array_rand($resolutions)]);
            $selenium->disableImages();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();
            $selenium->http->GetURL($this->ConfirmationNumberURL([]));

            $conf = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@placeholder, 'Booking code')]"), 30);

            if (empty($conf)) {
                $this->logger->error('conf input is not on the page');
                $this->savePageToLogs($selenium);

                return $this->notifications($arFields);
            }
            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            $lastName = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@placeholder, 'Last name')]"), 0);
            //$conf->sendKeys($arFields['ConfNo']);
            $mover->sendKeys($conf, $arFields['ConfNo'], 7);

            //$lastName->sendKeys($arFields['LastName']);
            $mover->sendKeys($lastName, $arFields['LastName'], 7);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@type="submit" and @aria-label="bookingPNR.ctaLabel"]'), 0);
            sleep(1);
            $this->savePageToLogs($selenium);

            if ($button) {
                $button->click();
            } else {
                return null;
            }

            $selenium->waitForElement(WebDriverBy::xpath('
                //input[@id = "id_tripResTitle"]
                | //small[contains(@class, "ui-form-group__error")]
            '), 15);

            $details = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'id_tripResTitle']"), 0);
            $this->savePageToLogs($selenium);

            $upgrade = $selenium->waitForElement(WebDriverBy::xpath("//a[@id = 'close-upgrade-seat']"), 0);

            if ($upgrade) {
                $upgrade->click();
            }
            $accept = $selenium->waitForElement(WebDriverBy::id("onetrust-accept-btn-handler"), 5);

            if ($accept) {
                $accept->click();
            }

            if ($details) {
                $it = $this->ParseItinerary();
            } else {
                if ($selenium->waitForElement(WebDriverBy::xpath("//button[@aria-label='common.contactMissingDetails.mainCta']"), 7)) {
                    try {
                        $selenium->driver->executeScript("Array.from(document.querySelectorAll('button[aria-label=\"common.contactMissingDetails.mainCta\"]')).forEach(button=>button.click())");
                    } catch (UnrecognizedExceptionException $e) {
                        $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage());
                    }
                }
                $this->savePageToLogs($selenium);

                if ($selenium->waitForElement(WebDriverBy::xpath("//h3[contains(.,'Your upcoming flights')]"), 13)) {
                    if ($close = $selenium->waitForElement(WebDriverBy::id('close-upgrade-seat'), 10)) {
                        $close->click();
                    }
                    sleep(2);
                    $this->savePageToLogs($selenium);
                    $it = $this->ParseItineraryNew($selenium);
                } elseif ($error = $selenium->waitForElement(WebDriverBy::xpath('//small[contains(@class, "ui-form-group__error")]'), 0)) {
                    $message = $error->getText();
                    $this->logger->error("[Error]: {$message}");

                    if (strstr($message, 'Please provide valid booking code format (valid')) {
                        return $message;
                    }
                } elseif ($error = $selenium->waitForElement(WebDriverBy::xpath('//p[contains(@class, "ui-alert__message")]'), 0)) {
                    $message = $error->getText();
                    $this->logger->error("[Error]: {$message}");

                    if (strstr($message, 'We have not been able to find any booking in our system under the information you provided.')) {
                        return $message;
                    }

                    if (strstr($message, 'We were unable to locate a reservation that matches the information you provided.')) {
                        return $message;
                    }
                }
            }
            $this->savePageToLogs($selenium);
        } finally {
            $selenium->http->cleanup();
        }

        return null;
    }
}
