<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerRotana extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $profile;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->setProxyBrightData();
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address', ACCOUNT_INVALID_PASSWORD);
        }
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->RetryCount = 0;

        $this->http->setCookie("aws-waf-token", "77b378ad-8ea3-4945-9e68-f857ebf7c2c1:EQoAtBhDA88HAAAA:J7J5j14Sxo908zujWt4H2lT++sIxO5EoSQAN8GrZ0ehGn5esXKMHlve+t4Z8HdK3n8iY3515rQaFs1smPfoEyNA5cXoVooBVE+2orOkfbbXjHD37FBcPBYF4lPLZVHOdyhfNGS2BJ8mjkyvg/hFtFAOAyKdlFVa5ATOND6c3q08wLdZg6lGJ74sTWQp2D4AY4G2QPg==", ".bookings.rotana.com");

        $this->http->GetURL('https://bookings.rotana.com/en/myaccount/reward');

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }
//        $isInvalidCredentials = $this->http->FindPreg("#AlertMessage\('invalidMemberDetails','https://bookings\.rotana\.com/'\);#");

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "username"         => $this->AccountFields['Login'],
            "password"         => $this->AccountFields['Pass'],
            "googleReCaptcha"  => $captcha,
            "customer_type"    => "1",
            "is_membership"    => "1",
            "dpmembershiptype" => "",
        ];
        $headers = [
            "Accept"           => "*/*",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL('https://bookings.rotana.com/ajax/userLogin', $data, $headers);

        if ($this->http->FindPreg('/^failure$/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException('Incorrect Email or Password. Please enter correct details.', ACCOUNT_INVALID_PASSWORD);
        }

        return true;
    }

    public function Login()
    {
        if ($this->http->FindPreg('/^captchafailure##/')) {
            $this->DebugInfo = 'captchafailure';
            $this->logger->error("captchafailure");
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 3, self::CAPTCHA_ERROR_MSG);
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

//        Incorrect Email or Password. Please enter correct details.

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - ... Rotana Rewards
        $this->SetBalance($this->http->FindSingleNode("//h2[@id = 'user_points_data']"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//a[contains(@href, \"myaccount/edit\")]/preceding-sibling::text()[1]")));
        // Current Tier
        $this->SetProperty('Status', $this->http->FindSingleNode("//h2[@id = 'user_points_data']/following-sibling::p[contains(text(),'Rotana Rewards')]", null, true, "/Rotana Rewards (.+?)$/"));
        // @name='membershipno'
        $this->SetProperty('CardNumber', $this->http->FindSingleNode("//li[contains(text(), 'Your Membership:')]/span", null, true, "/([^\•]+)/"));
        // Open Reservations (new)
        $this->SetProperty('TotalBookings', $this->http->FindSingleNode('//p[contains(.,"Total Bookings")]/preceding-sibling::h2'));
    }

    public function GetConfirmationFields()
    {
        return [
            'ConfNo' => [
                'Caption'  => 'Reservation Number',
                'Type'     => 'string',
                'Size'     => 32,
                'Required' => true,
            ],
            'Email'  => [
                'Caption'  => 'Email',
                'Type'     => 'string',
                'Size'     => 50,
                'Value'    => $this->GetUserField('Email'),
                'Required' => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://bookings.rotana.com/en/myaccount/modifyreservation';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("modificationFormr")) {
            $this->sendNotification("rotana - failed to retrieve itinerary by conf #");

            return null;
        }
        $this->http->SetInputValue('email', $arFields['Email']);
        $this->http->SetInputValue('reservation_id', $arFields['ConfNo']);

        if (!$this->http->PostForm()) {
            return null;
        }

        if ($message = $this->http->FindSingleNode("//div[@id = 'error_message_div']")) {
            $this->sendNotification("#20131 rotana, retrieve errors: {$message}");

            return $message;
        }
        $this->sendNotification("#20131 rotana, retrieve found  - Email: {$arFields['Email']}, ConfNo: {$arFields['ConfNo']}");

        /*
                $it = $this->ParseHotelReservation();
                $it = array($it);
        */

        return null;
    }

    /*    function ParseHotelReservation() {
            $this->logger->notice(__METHOD__);
            $result['Kind'] = 'R';
            $result['ConfirmationNumber'] = $this->http->FindSingleNode("//span[contains(text(), 'Reservation number')]", null, false, '/number\s+([\w-]+)/i');
            $result['HotelName'] = $this->http->FindSingleNode("//div[@class='formdata']/table//tr[1]/td[@colspan=4]");

            $result['CheckInDate'] = strtotime($this->http->FindSingleNode("//div[@class='formdata']/table//td[normalize-space(text())='Arrival']/following-sibling::td[1]"), false);
            $result['CheckOutDate'] = strtotime($this->http->FindSingleNode("//div[@class='formdata']/table//td[normalize-space(text())='Departure']/following-sibling::td[1]"), false);

            $result['Guests'] = $this->http->FindSingleNode("//div[@class='formdata']/table//td[normalize-space(text())='Adults']/following-sibling::td[1]");
            $result['Kids'] = $this->http->FindSingleNode("//div[@class='formdata']/table//td[normalize-space(text())='Children']/following-sibling::td[1]");

            $result['RateType'] = $this->http->FindSingleNode("//div[@class='formdata']/table//td[normalize-space(text())='Rate']/following-sibling::td[1]");
            $result['RoomType'] = $this->http->FindSingleNode("//div[@class='formdata']/table//td[normalize-space(text())='Room Type']/following-sibling::td[1]");
            $result['RoomTypeDescription'] = $this->http->FindSingleNode("//p[strong[text()='Room Description']]/following-sibling::p[1]");

            $result['Cost'] = $this->http->FindSingleNode("//div[@class='formdata']/table//td[normalize-space(text())='Room Total']/following-sibling::td[1]", null, false, '/\s+([\d.,]+)/');

            $total = $this->http->FindSingleNode("//div[@class='formdata']/table//td[starts-with(normalize-space(text()),'Total in ')]/following-sibling::td[1]");

            $result['Currency'] = $this->http->FindPreg('/([A-Z]{3})\s+/', false, $total);
            $result['Total'] = $this->http->FindPreg('/\s+([\d.,]+)/', false, $total);

            $result['CancellationPolicy'] = $this->http->FindSingleNode("//p[strong[text()='Cancellation Policy']]/following-sibling::p[1]");

            if (!empty($result['HotelName'])) {
                $result['Address'] = $result['HotelName'];
            }

            return $result;
        }

        private function fetchVars($varKey, $valKey = null)
        {
            $str = '';
            $var = $this->http->FindPreg('/' . $varKey . '\s*=\s*"(.*)"/U');
            if (!empty($var)) {
                $str .= $var;
                if (!empty($valKey)) {
                    $val = $this->http->FindPreg('/' . $valKey . '\s*=\s*"(.*)"/U');
                    if (!empty($val)) {
                        $str .= '=' . $val;
                    } else {
                        $val = $this->http->FindPreg('/' . $valKey . '\s*=\s*(.*);/U');
                        if (!empty($val)) {
                            $str .= '=' . $val;
                        } else {
                            $this->logger->debug('fetchVars() not found js VALUE: ' . $varKey);
                        }
                    }
                }
            } else {
                $this->logger->debug('fetchVars() not found js VARIABLE: ' . $varKey);
            }

            return $str;
        }*/

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->http->FilterHTML = false;
        // New
        $newRes = $this->http->XPath->query('//div[@id="reservation-list"]/div[@class = "openRes"]/div[contains(@id,"myaccount-content-")]');
        // Past
        $pastRes = $this->http->XPath->query('//div[@id="reservation-list"]/div[@class = "pastRes"]/div[contains(@id,"myaccount-content-")]');
        // Cancelled
        //$cancelledRes = $this->http->XPath->query('//div[@id="reservation-list"]/div[@class = "cancelledRes"]/div[contains(@id,"myaccount-content-")]');
        // NoItineraries
        $noItineraries = $this->http->FindSingleNode("//div[@id = 'reservation-list' and normalize-space() = 'No reservation found.']");

        // SetNoItineraries
        if (
            $newRes->length == 0
            && $noItineraries
            && (
                !$this->ParsePastIts
                || $pastRes->length == 0)
        ) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }
        // New
        $this->parseItineraryPage('openresview');
        // Cancelled
        $this->parseItineraryPage('cancelresview');
        // Past
        if ($this->ParsePastIts) {
            $this->parseItineraryPage('pastresview');
        }

        return [];
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/grecaptcha\.execute\('([^\']+)',\s*\{action:\s*'validate_captcha'\}/");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "v3",
            "action"    => "validate_captcha",
            "min_score" => 0.7,
        ];

        if ($this->attempt == 1) {
            $postData = [
                "type"         => "RecaptchaV3TaskProxyless",
                "websiteURL"   => $this->http->currentUrl(),
                "websiteKey"   => $key,
                "minScore"     => 0.7,
                "pageAction"   => "validate_captcha",
            ];
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $this->recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://bookings.rotana.com/en/myaccount/myaccount');
        $this->http->RetryCount = 2;

        if (
            $this->http->FindSingleNode('(//div[@id="site-navigation"]/descendant::a[contains(@href, "logout")]/@href)[1]')
            && $this->http->FindSingleNode("//h2[@id = 'user_points_data']/following-sibling::p[contains(text(),'Rotana Rewards')]")
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseItineraryPage($type)
    {
        $this->logger->info("{$type}", ['Header' => 2]);
        $next = false;
        $page = 1;

        do {
            $this->logger->info("Itinerary page #{$page}", ['Header' => 3]);
            $this->logger->debug("[Page: {$page}]");
            $this->http->GetURL("https://bookings.rotana.com/en/myaccount/{$type}/page/{$page}");
            $newRes = $this->http->XPath->query('//div[@id="bookings-container"]/div[contains(@id,"myaccount-content-")]');

            if ($newRes->length > 0) {
                foreach ($newRes as $k => $root) {
                    if ($type == 'openresview') {
                        $this->sendNotification('openresview it // MI');
                    }
                    $this->ParseHotel($root, $type);
                }
            }
            $next = !$this->http->FindSingleNode("//ul[@class='pagination']/li[@class='disabled' and normalize-space()='»']")
                && $newRes->length > 0
                && $page < 10;
            $this->logger->debug("[Next: {$next}]");
            $page++;
        } while ($next);
    }

    private function ParseHotel($root, $description)
    {
        // Reservation Number
        $rNo = $this->http->FindSingleNode('./@id', $root, true, "/myaccount-content-(.+?)$/");
        $this->logger->info("Parse {$description} Itinerary #{$rNo}", ['Header' => 4]);
        $h = $this->itinerariesMaster->add()->hotel();

        $h->general()->confirmation($rNo, "Reservation Number", true);

        // CRS Confirmation Number
        $crsNo = $this->http->FindSingleNode('descendant::div[@class ="reserve_info"]/descendant::strong[contains(text(),"CRS Confirmation Number:")]/following-sibling::span', $root);

        if (isset($crsNo)) {
            $h->ota()->confirmation($crsNo, "CRS Confirmation Number");
        }
        // Status
        $status = $this->http->FindSingleNode('descendant::span[contains(@id,"' . $rNo . '_status")]', $root);
        $h->general()->status($status);

        if ($status == "Cancelled") {
            $h->general()->cancelled();
        }
        // Date
        $date = $this->http->FindSingleNode('descendant::span[contains(@id,"' . $rNo . '_bookingDate")]', $root);

        if (!$date) {
            $date = $this->http->FindSingleNode(".//strong[contains(text(),'Booking made:')]/following-sibling::span", $root);
        }

        $this->logger->debug("date: $date");
        $h->general()->date2(str_replace(['/', '@'], ['.', ' '], $date));

        $h->hotel()
            ->name($this->http->FindSingleNode('descendant::span[contains(@id,"' . $rNo . '_hotelName")]', $root))
            ->address($this->http->FindSingleNode('descendant::span[contains(@id,"' . $rNo . '_hotelAddress")]', $root));
        // Rooms
        $rooms = $this->http->FindSingleNode('descendant::span[contains(@id,"' . $rNo . '_rooms")]', $root);

        if (!$rooms) {
            $rooms = $this->http->FindSingleNode(".//strong[contains(text(),'Total Number Of Rooms:')]/following-sibling::span", $root);
        }
        $h->booked()->rooms($rooms);
        // CheckIn
        $checkIn = $this->http->FindSingleNode('descendant::span[contains(@id,"' . $rNo . '_checkIn")]', $root, true, "/,\s+([A-z]+,\s\d{1,2},\s\d{4})/");
        $this->logger->debug("checkIn: $checkIn");
        $h->booked()->checkIn2(str_replace(',', " ", $checkIn));

        // CheckOut
        $checkOut = $this->http->FindSingleNode('descendant::span[contains(@id,"' . $rNo . '_checkOut")]', $root, true, "/,\s+([A-z]+,\s\d{1,2},\s\d{4})/");
        $this->logger->debug("checkOut: $checkOut");
        $h->booked()->checkOut2(str_replace(',', " ", $checkOut));

        // View Reservation
        $url = $this->http->FindSingleNode('descendant::a[contains(@href,"viewReservation/' . $rNo . '")]/@href', $root);
        $this->logger->debug("url: " . $url, ['pre' => true]);

        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $this->http->NormalizeURL($url);
        $this->http->RetryCount = 0;
        $http2->GetURL($url);
        $this->http->RetryCount = 2;

        // Guest Details
        $guestDetails = $http2->FindSingleNode('//td[contains(text(),"Guest Details")]/following-sibling::td');
        $this->logger->debug(var_export($guestDetails, true), ['pre' => true]);

        if (preg_match_all('/(?:M[A-z]{1,3})[\.]?[\s]?([A-z\s]+?)(?:[\(,]|$)/m', $guestDetails, $m)) {
            $this->logger->debug(var_export($m[1], true), ['pre' => true]);
            $h->general()->travellers(array_unique($m[1]), false);
        }

        // Cancellation Policy
        $cancellationText = $http2->FindSingleNode('//td[contains(text(),"Cancellation Policy")]/following-sibling::td');

        if ($cancellationText) {
            $h->general()->cancellation($cancellationText);
        }

        if (preg_match("/If amended \/ cancelled before (\d{1,2}:\d{1,2}) on (\d{1,2}\s[A-z]{3}\s\d{4}) no fees will be charged\./i", $cancellationText, $m)) {
            $h->booked()->deadline2($m[1] . " " . $m[2]);
        } elseif (strstr($cancellationText, 'Non-refundable')) {
            $h->booked()->nonRefundable();
        }
        $this->logger->debug(var_export($http2->FindSingleNode('//td[contains(text(),"Adult") and contains(text(),"Children")][normalize-space()]'), true), ['pre' => true]);
        // Guests
        $h->booked()->guests($http2->FindSingleNode('//td[contains(text(),"Adult") and contains(text(),"Children")][normalize-space()]', null, true, "/\(\s*Adult\s*(\d+)/"), false, true);
        // Kids
        $h->booked()->kids($http2->FindSingleNode('//td[contains(text(),"Adult") and contains(text(),"Children")][normalize-space()]', null, true, "/Children\s*(\d+)\s*\)/"), false, true);
        // Cost
        $h->price()->cost(PriceHelper::cost($http2->FindSingleNode('//td[contains(text(),"Total Room Amount")]/following-sibling::td', null, true, "/[A-Z]{3}\s?([\d,.]+)/")));
        // Currency
        $h->price()->currency($http2->FindSingleNode('//td[contains(text(),"Total Room Amount")]/following-sibling::td', null, true, "/([A-Z]{3})\s?[\d,.]+/"));
        // Total
        $h->price()->total(PriceHelper::cost($http2->FindSingleNode('//td[contains(text(),"Gross Total")]/following-sibling::td', null, true, "/[A-Z]{3}\s?([\d,.]+)/")));

        // Fees
        $fees = $http2->FindNodes('//*[contains(text(),"Exclusive Taxes :-")]/ancestor::td/ul/li');
        $this->logger->debug(var_export($fees, true), ['pre' => true]);

        foreach ($fees as $item) {
            if (preg_match("/^(.+?)[\s+-]?\s+[A-Z]{3}\s?([\d,.]+)$/", $item, $m)) {
                $h->price()->fee($m[1], PriceHelper::cost($m[2]));
            }
        }

        return true;
    }
}
