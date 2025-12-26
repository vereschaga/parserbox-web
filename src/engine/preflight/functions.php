<?php

use AwardWallet\Common\Parser\Util\PriceHelper;

class TAccountCheckerPreflight extends TAccountChecker
{
//    public static function GetAccountChecker($accountInfo) {
//        require_once __DIR__."/TAccountCheckerPreflightSelenium.php";
//        return new TAccountCheckerPreflightSelenium();
//    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.preflightairportparking.com/site/account/login");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json",
            "Origin"       => "https://www.preflightairportparking.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://pfapi.intpark.com/api/Account/UserLogin/v2", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Currently undergoing maintenance
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Currently undergoing maintanence")]')) {
            throw new CheckException('Currently undergoing maintenance', ACCOUNT_PROVIDER_ERROR);
        }
        // The System is down for maintenance. Please try again later.
        $this->http->GetURL("https://www.preflightairportparking.com");

        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'The System is down for maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")
            // Server Error in '/' Application
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->data->AuthToken)) {
            $this->http->setCookie("Auth", $response->data->AuthToken, ".www.preflightairportparking.com");
            $this->http->setCookie("CardID", $response->data->CardID, ".www.preflightairportparking.com");
            $this->http->setCookie("AngularLogin", "1", ".www.preflightairportparking.com");

            $headers = [
                "Accept"        => "application/json, text/plain, */*",
                "Origin"        => "https://www.preflightairportparking.com",
                "Authorization" => "Bearer " . $response->data->AuthToken,
            ];
            $this->http->GetURL("https://pfapi.intpark.com/api/Account/UserInfo", $headers);
            $response = $this->http->JsonLog();
            $data = $response->data->UserInfo;

            $this->http->setCookie("CardNumber", $data->CardInfo->CardNumber, ".www.preflightairportparking.com");

            if ($message = $this->http->FindSingleNode('//h3[contains(text(), "re sorry, an error has occurred with your request.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return true;
        }

        if (
            $this->http->FindNodes('//a[contains(text(), "LOGOUT")]')
            || $this->http->getCookieByName("Auth")
        ) {
            $this->http->GetURL("https://www.preflightairportparking.com/members/AccountInfo.aspx");

            return true;
        }

        // Invalid login or password
        if ($message = $this->http->FindSingleNode('//span[@class="clsErrorMsg"]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid login or password
        if ($message = $this->http->FindPreg("/Please login using your Card Number and Password/ims")) {
            throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
        }
        // Username or Password is incorrect
        if ($message = $this->http->FindPreg("/(Username or Password is incorrect\.\s*Please try again\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Update Account Security
        if ($this->http->FindSingleNode("//strong[contains(text(), 'Update Account Security')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'UPDATE ACCOUNT SECURITY')]")) {
            $this->throwProfileUpdateMessageException();
        }
        // We're sorry, an error has occurred with your request.
        if ($message = $this->http->FindSingleNode('//h3[contains(text(), "We\'re sorry, an error has occurred with your request.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Content-Type"  => "application/json",
            "Origin"        => "https://www.preflightairportparking.com",
            "Authorization" => "Bearer " . $this->http->getCookieByName("Auth", ".www.preflightairportparking.com"),
        ];
        $response = $this->http->JsonLog(null, 0);
        $data = $response->data->UserInfo;

        // Name
        $this->SetProperty("Name", beautifulName($data->acctName));
        // Account Number
        $this->SetProperty("Number", $data->ffAccountNumber);
        // Points Earned Life Time
        $this->SetProperty("PointsEarned", $data->pointsEarnedLifetime);
        // Balance - Points Available for New Awards
        $points = $data->pointsAvailable;

        if (isset($points)) {
            $points = preg_replace('/([^\d\.])/ims', '${2}', $points);
        }
        $this->SetBalance($points);
        // Points Expiring in Next 60 Days
        $this->SetProperty("PointsExpiring", $data->pointsExpiring60days);

        // Expiration Date  // refs #4936
        if (isset($points) && $points > 0) {
            $this->http->GetURL("https://pfapi.intpark.com/api/Award/GetPointTransHistory", $headers);
            $nodes = $this->http->JsonLog()->data->HistoryList ?? [];
            $this->logger->debug("Total nodes found " . count($nodes));

            for ($i = count($nodes) - 1; $i >= 0; $i--) {
                $historyPoints = $nodes[$i]->TranPoints ?? null;
                $this->logger->debug("Node # $i - Balance: $points, HistoryPoints: $historyPoints");

                if ($historyPoints > 0) {
                    $points -= $historyPoints;
                }
                $this->logger->debug("Node # $i - Balance: $points / round: " . round($points, 2));

                if (round($points, 2) <= 0) {
                    $date = $nodes[$i]->TranDate ?? null;

                    if (isset($date)) {
                        $this->SetProperty("EarningDate", date("n/j/Y", strtotime($date)));
                        $this->SetExpirationDate(strtotime("+3 year", strtotime($date)));
                    }
                    // Points Expiring
                    $this->SetProperty("PointsToExpire", round(($points + $nodes[$i]->TranPoints), 2));

                    break;
                }// if ($points <= 0)
            }// for ($i = 0; $i < $historyPoints->length; $i++)
        }// if (isset($points))
    }

    public function ParseItineraries()
    {
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/json',
            "Authorization" => "Bearer " . $this->http->getCookieByName("Auth", ".www.preflightairportparking.com"),
        ];
        /*$this->http->GetURL('https://pfapi.intpark.com/api/Reservation/GetUpcomingReservations', $headers);
        $upcoming = $this->http->JsonLog();

        if ($upcoming->data->ReservationList != null) {
            $this->sendNotification("future itinearies were found");
        }*/

        $data = [
            "StartDT" => date('Y-n-j', strtotime('-3 year')),
            "EndDT"   => date('Y-n-j', strtotime('+1 year -1 month')),
        ];
        $this->http->PostURL('https://pfapi.intpark.com/api/Reservation/GetReservationHistory', json_encode($data), $headers);

        $receipts = [];
        $nodes = $this->http->JsonLog()->data->ReservationHistoryList ?? [];
        $this->logger->debug("Total " . count($nodes) . " itineraries were found");

        foreach ($nodes as $node) {
            $endDate = strtotime($node->ReservationEndDT);
            $confNo = $node->ReceiptCD;

            if (!$confNo || !$endDate) {
                $this->logger->error('empty receipt or date');

                return [];
            }

            if ($endDate < strtotime('now') && !$this->ParsePastIts) {
                $this->logger->notice('Past parking, skip it');

                continue;
            }
            $receipts[] = $confNo;
        }

        foreach ($receipts as $confNo) {
            $data = [
                'ReceiptCD' => $confNo,
            ];
            $this->http->PostURL("https://pfapi.intpark.com/api/Reservation/GetExistingReservationByCD", json_encode($data), $headers);
            $this->parseItineraryReceipt($this->http->JsonLog());
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Confirmation Number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 20,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://www.preflightairportparking.com/Reservation-Edit.aspx';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification('check retrieve // MI');
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("aspnetForm")) {
            $this->sendNotification('failed to retrieve itinerary by conf');

            return null;
        }
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$tbResID', $arFields['ConfNo']);
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$tbLName', $arFields['LastName']);

        if (!$this->http->PostForm()) {
            return null;
        }

        $url = $this->http->FindSingleNode("//a[contains(@href,'preflight-reservation-prepay-confirmation.aspx?ReceiptCD={$arFields['ConfNo']}')]/@href");

        if (!isset($url)) {
            $this->sendNotification('failed to retrieve url');

            return self::CONFIRMATION_ERROR_MSG;
        }
        $this->http->NormalizeURL($url);
        $this->http->GetURL($url);
        $this->parseItinerary($arFields["ConfNo"]);

        return null;
    }

    private function parseItinerary($receipt)
    {
        $this->logger->notice(__METHOD__);
        $p = $this->itinerariesMaster->createParking();
        $this->logger->info("Parse Itinerary #{$receipt}", ['Header' => 3]);
        $p->general()->confirmation($receipt);
        $p->general()->traveller(beautifulName($this->http->FindSingleNode("//span[@id='ctl00_ContentPlaceHolder1_lbFName']")));
        $p->program()->account($this->http->FindSingleNode("//span[@id='ctl00_ContentPlaceHolder1_lbFPNum']"), false);

        $p->place()->location($this->http->FindSingleNode(
            "//td[normalize-space(text())='Location:']/../following-sibling::tr[1]/td/text()[1]"));

        $address = '';
        $nodes = $this->http->FindNodes("//td[normalize-space(text())='Location:']/../following-sibling::tr[1]/td/table//tr[not(@class='hidden-print')]");

        foreach ($nodes as $node) {
            if ($this->http->FindPreg('/^[\d.,]{7,}$/', false, $node)) {
                $p->place()->phone($node);
            } else {
                $address .= ' ' . $node;
            }
        }
        $p->place()->address($address);

        $p->booked()->start2($this->http->FindSingleNode("//span[@id='ctl00_ContentPlaceHolder1_lbResDepDT']"));
        $p->booked()->end2($this->http->FindSingleNode("//span[@id='ctl00_ContentPlaceHolder1_lbResRetDT']"));

        $p->price()->spentAwards($this->http->FindSingleNode("//span[@id='ctl00_ContentPlaceHolder1_lbFPApplied']", null, false, '/You have chosen to apply (\d+) frequent parker points/'));

        $tax = $this->http->FindSingleNode("//span[@id='ctl00_ContentPlaceHolder1_lbChargeTaxes']", null, false, '/^.([\d.,\s]+)$/');
        $p->price()->tax($tax);

        $total = $this->http->FindSingleNode("//span[@id='ctl00_ContentPlaceHolder1_lbReservationTotal']");
        $this->logger->debug($total);
        // $34.49
        if (preg_match("/^(.)([\d.,\s]+)$/", $total, $m)) {
            $p->price()
                ->total(PriceHelper::cost($m[2]))
                ->currency($this->currency($m[1]));
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($p->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryReceipt($receipt)
    {
        $data = $receipt->data;
        $this->logger->notice(__METHOD__);
        $address = '';

        if (mb_strlen(trim($data->ResAddress)) > 1) {
            $address = $data->ResAddress;
        }

        if (mb_strlen(trim($data->ResAddress)) > 1) {
            $address .= ',' . $data->ResCityStateZip;
        }

        if (empty($address)) {
            $this->logger->error('Skip: no address');

            return;
        }
        $p = $this->itinerariesMaster->createParking();
        $this->logger->info("Parse Itinerary #{$data->ReceiptCD}", ['Header' => 3]);
        $p->place()->address($address);
        $p->general()->confirmation($data->ReceiptCD);
        $p->general()->traveller(beautifulName($data->FName));
        $p->program()->account(str_replace('-', '', $data->FPNum), false);

        $p->booked()->start2($data->StartDT);
        $p->booked()->end2($data->EndDT);

        $p->price()->tax($data->Taxes);

        if ($data->StandAloneAirportFee > 0) {
            $p->price()->fee('Airport Fee', $data->StandAloneAirportFee);
        }

        if ($data->ReservationFee > 0) {
            $p->price()->fee('Reservation Fee', $data->ReservationFee);
        }

        if ($data->TotalAmtRefunded > 0) {
            $this->sendNotification('TotalAmtRefunded > 0 // MI');
            //$p->price()->spentAwards();
        }

        if (preg_match("/^(.)([\d.,\s]+)$/", $data->ChargeTotal, $m)) {
            $p->price()
                ->total(PriceHelper::cost($data->TotalAmtPaid))
                ->currency($this->currency($m[1]));
        }
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
