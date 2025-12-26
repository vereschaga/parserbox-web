<?php

use AwardWallet\ItineraryArrays\CarRental;

class TAccountCheckerThrifty extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.thrifty.com/BlueChip/SignIn.aspx");

        if ($this->http->Response['code'] == 404) {
            $this->http->GetURL("https://www.thrifty.com/BlueChip/SignIn.aspx");
        }

        if (!$this->http->ParseForm("form1")) {
            return $this->checkErrors();
        }

        $this->http->Form['content_0$BlueChipLogin$BlueChipIDTextBox'] = $this->AccountFields['Login'];
        $this->http->Form['content_0$BlueChipLogin$PasswordTextBox'] = $this->AccountFields['Pass'];
        $this->http->Form['content_0$BlueChipLogin$SignInSitecoreImageButton.x'] = '30';
        $this->http->Form['content_0$BlueChipLogin$SignInSitecoreImageButton.y'] = '7';
        $this->http->Form['content_0$BlueChipLogin$RememberMeCheckBox'] = 'on';

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# An error occurred while processing request
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'An unexpected error has occurred')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error - Read
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing request
        if ($message = $this->http->FindPreg('/(An error occurred while processing your request)/ims')) {
            throw new CheckException("An error occurred while processing your request.", ACCOUNT_PROVIDER_ERROR);
        }
        //# The server encountered an internal error or misconfiguration and was unable to complete your request.
        if ($message = $this->http->FindPreg('/(The server encountered an internal error or misconfiguration and was unable to complete your request.)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are experiencing technical difficulties.
        if ($message = $this->http->FindPreg('/<H1>Server Error in \'\/\' Application/')) {
            $this->http->GetURL("https://www.thrifty.com/");

            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are experiencing technical difficulties.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm([], 120)) {//todo:: debug
            return $this->checkErrors();
        }
        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@id, '_LogOutLinkButton')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[contains(@id, 'ErrorLabel')]")) {
            $this->logger->error("[Error]: {$message}");

            if (
                stristr($message, 'The password you specified is not correct. Please verify that you entered it correctly.')
                || stristr($message, 'We were unable to find your Blue Chip number. Please verify that you entered it correctly.')
                || stristr($message, 'The Blue Chip ID you entered is invalid. Please re-enter your ID number (You may try adding a “0” before the number).')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                stristr($message, 'We are having difficulty completing your request, as there are certain parts of your profile that are incomplete. Please contact the Web Support Desk')
                || stristr($message, 'We\'re sorry; our system encountered a temporary problem in processing your request.')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                stristr($message, 'Your account is locked. Information has been entered incorrectly multiple times against your account.')
            ) {
                throw new CheckException("Your account is locked. Information has been entered incorrectly multiple times against your account.", ACCOUNT_LOCKOUT);
            }

            if (stristr($message, 'For security reasons, please verify your account details and Click here to reset your password')) {
                $this->throwProfileUpdateMessageException();
            }

            return false;
        }
        // Create a Password
        if ($message = $this->http->FindSingleNode("//span[contains(@id, 'CreatePasswordHeaderLabel') and contains(text(), 'Create a Password.')]") || $this->http->currentURL() === 'https://www.thrifty.com/bluechip/ChangePassword.aspx') {
            throw new CheckException("Thrifty (Blue Chip Rewards) website is asking you to create your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->checkErrors();

        // Retry login
        if (preg_match("/sitecore\/service\/noaccess\.aspx/ims", $this->http->currentUrl())) {
            throw new CheckRetryNeededException(3, 10);
        }

        if ($this->http->currentUrl() == 'https://www.thrifty.com/us/en') {
            throw new CheckRetryNeededException(3, 10);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != 'https://www.thrifty.com/bluechip/index.aspx') {
            $this->http->GetURL("https://www.thrifty.com/bluechip/index.aspx");
        }
        // Name
        $name = $this->http->FindSingleNode("//span[contains(@id, 'WelcomeBackLabel')]", null, true, "/back, ([a-zA-Z0-9]+)/i");

        if (isset($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // Balance - Total Point Balance
        $balance = $this->http->FindSingleNode("//span[contains(@id, 'TotalPoints') and not(contains(text(), 'Balance'))]");
        $this->logger->debug("Balance Set: '{$balance}'");
        //# Balance
        if ($balance == 'Unavailable At This Time') {
            $this->SetWarning($balance);
        } elseif (isset($balance) && $balance != '') {
            $this->SetBalance($balance);
        } elseif ($this->http->FindSingleNode("//span[contains(text(), 'Join Blue Chip Rewards')]/following::div[1]/p[contains(text(), 'Blue Chip Rewards is an optional rewards program that gives you credits towards a free rental day at Thrifty, rather than airline miles. Learn More')]
            | //a[contains(text(), 'Join Blue Chip Rewards')]")
        ) {
            $this->SetBalanceNA();
        }
        //# If Balance not found
        else {
            throw new CheckException("We were able to successfully log into your account; however, we are not able to find your account balance on thrifty.com", ACCOUNT_PROVIDER_ERROR);
        }

        // Expiration Date   // refs #5958
        $nodes = $this->http->XPath->query("//table[@id = 'content_0_MainMemberLoggedIn_RewardsMemberModule_RewardsMemberModule_MemberModuleGridView']//tr[td]");
        $this->http->Log("Total nodes found: " . $nodes->length);

        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $rewards = $this->http->FindSingleNode('td[4]', $nodes->item($i));
            $rewardsUsed = $this->http->FindSingleNode('td[5]', $nodes->item($i));
            $date = $this->http->FindSingleNode('td[2]', $nodes->item($i));

            if (isset($rewardsUsed, $rewards, $date) && $rewards > $rewardsUsed) {
                $this->http->Log("Date: $date | Rewards: $rewards | Rewards Used: $rewardsUsed");
                //# Expiring Balance
                $this->SetProperty("ExpiringBalance", ($rewards - $rewardsUsed));
                $this->SetProperty("EarningDate", $date);

                if (strtotime($date)) {
                    $this->SetExpirationDate(strtotime("+1 year", strtotime($date)));
                }

                break;
            }// if (isset($rewardsUsed, $rewards) && $rewardsUsed > $rewards)
        }// for ($i = $nodes->length - 1; $i >= 0; $i--)

        $this->http->GetURL("https://www.thrifty.com/BlueChip/MembershipCard.aspx");

        if ($this->http->FindSingleNode("//div[@id='ErrorTopPanel' and contains(normalize-space(.),'Permission to the requested document') and contains(normalize-space(.),'was denied')]")) {
            throw new CheckException("We were able to successfully log into your account; however, we are not able to find details of your account on thrifty.com", ACCOUNT_PROVIDER_ERROR);
        }

        // Name
        $name = $this->http->FindSingleNode("//*[contains(@id, 'BCNumber')]/text()[1]");

        if (!empty($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // Blue Chip #
        if (!isset($this->Properties["AccountNumber"])) {
            $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//*[contains(@id, 'BCNumber')]/text()[last()]", null, true, '/\#(\d+)/'));
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['NoCookieURL'] = true;
        //$arg['CookieURL'] = 'https://www.thrifty.com/BlueChip/SignIn.aspx';
        $arg['SuccessURL'] = 'https://www.thrifty.com/bluechip/index.aspx';

        return $arg;
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://www.thrifty.com/bluechip/index.aspx");

        if ($this->http->FindSingleNode("//span[contains(text(), 'No pending reservations found.')]")) {
            return $this->noItinerariesArr();
        }

        //		$this->http->GetURL("https://www.thrifty.com/bluechip/RentalHistory.aspx");

        $this->http->ParseForm('form1');
        $form = $this->http->Form;
        $reservations = $this->http->FindNodes("//table[contains(@id, 'PendingReservations')]//a[contains(text(), 'View')]/@href");
        $countReservations = count($reservations);
        $this->logger->debug("Found reservations: " . $countReservations);
        //		if ($countReservations > 0 || !$this->http->FindNodes("//text()[contains(.,'DRIVERS LICENSE EXPIRED') or contains(.,'CREDIT CARD EXPIRED')]"))
        //			$this->sendNotification("thrifty - find itinerary // MI");
        for ($i = 0; $i < $countReservations; $i++) {
            if (preg_match('/javascript:__doPostBack\(\'([^\']*)\',\'([^\']*)\'\)/ims', $reservations[$i], $matches)) {
                $this->http->Form = $form;
                $this->http->setDefaultHeader("content-type", "application/x-www-form-urlencoded; charset=utf-8");
                $this->http->FormURL = 'https://www.thrifty.com/bluechip/index.aspx';
                $this->http->Form['__EVENTTARGET'] = $matches[1];
                $this->http->Form['__EVENTARGUMENT'] = $matches[2];
                $this->http->PostForm();
                $this->http->Form = [];

                if ($this->http->ParseForm('Form1')) {
                    $this->http->Form['ReservationAuthenticationControl$SubmitButton.x'] = '12';
                    $this->http->Form['ReservationAuthenticationControl$SubmitButton.y'] = '12';
                    $this->http->PostForm();
                }
                //				$this->http->GetURL("https://www.thrifty.com".$reservations->item($i)->nodeValue);
                $res = $this->ParseConfirmationThrifty();

                if (is_array($res) && count($res) > 0) {
                    $result[] = $res;
                }
            }
        }

        return $result;
    }

    public function ParseConfirmationThrifty()
    {
        /** @var CarRental $it */
        $it = ['Kind' => "L"];
        // ConfirmationNumber
        $it['Number'] = $this->http->FindSingleNode("//*[contains(@id, 'ConfirmationNumberDisplay')]", null, true, '/\#?\s*(.*)/ims');
        //# PickupPhone
        $it['PickupPhone'] = $this->http->FindSingleNode("//*[contains(@id, 'reservations_controls_reservationdetails_ascx1_PickupLocationTextLabel')]/text()[last()]");
        //# PickupLocation
        $it['PickupLocation'] = implode(', ', $this->http->FindNodes("//*[contains(@id, 'reservations_controls_reservationdetails_ascx1_PickupLocationTextLabel')]/text()"));
        $it['PickupLocation'] = str_replace($it['PickupPhone'], '', $it['PickupLocation']);
        $it['PickupLocation'] = preg_replace("/\s*\,\s*$/ims", '', $it['PickupLocation']);
        //# PickupDatetime
        $value = $this->http->FindSingleNode("//*[contains(@id, 'reservations_controls_reservationdetails_ascx1_PickupDateTextLabel')]");
        $this->http->Log("PickupDatetime: {$value}");

        if (preg_match('/\s*(.*)\s*(\d{2}:\d{2}\s*(PM|AM))/ims', $value, $matches)) {
            $it['PickupDatetime'] = strtotime(trim("$matches[1]  $matches[2]"));
        }
        //# DropoffLocation
        $it['DropoffLocation'] = $this->http->FindSingleNode("//*[contains(@id, 'reservations_controls_reservationdetails_ascx1_DropOffLocationTextLabel')]");

        if ($it['DropoffLocation'] == 'Same as pickup') {
            $it['DropoffLocation'] = $it['PickupLocation'];
        }

        //# DropoffDatetime
        $value = $this->http->FindSingleNode("//*[contains(@id, 'reservations_controls_reservationdetails_ascx1_DropOffDateTextLabel')]");
        $this->http->Log("DropoffDatetime: {$value}");

        if (preg_match('/\s*(.*)\s*(\d{2}:\d{2}\s*(PM|AM))/ims', $value, $matches)) {
            $it['DropoffDatetime'] = strtotime(trim("$matches[1]  $matches[2]"));
        }
        $it['CarModel'] = $this->http->FindSingleNode('//span[@id="reservations_controls_reservationdetails_ascx1_CarClassValueLabel"]');
        $it['CarType'] = $this->http->FindSingleNode("//*[contains(@id, 'reservations_controls_reservationdetails_ascx1_CarTypeTextLabel')]");
        $it['RenterName'] = $this->http->FindSingleNode('//span[@id="reservations_controls_reservationdetails_ascx1_RentersName"]');
        //# AccountNumbers
        $it['AccountNumbers'] = $this->http->FindSingleNode("//span[@id = 'reservations_controls_reservationdetails_ascx1_BlueChipNumber']");
        // TotalCharge
        $totalCharge = $this->http->FindSingleNode("//*[contains(@id, 'reservations_controls_reservationdetails_ascx1_EstimatedGrandTotal2')]", null, true, '/(\d+.\d+|\d+)/');

        if (!isset($totalCharge)) {
            $totalCharge = $this->http->FindSingleNode("//span[@id = 'reservations_controls_reservationdetails_ascx1_EstimatedGrandTotal']", null, true, '/(\d+.\d+|\d+)/');
        }

        if (isset($totalCharge)) {
            $it['TotalCharge'] = $totalCharge;
        }
        // Currency
        $currency = $this->http->FindSingleNode("//*[contains(@id, 'reservations_controls_reservationdetails_ascx1_EstimatedGrandTotalLabel')]", null, true, '/([A-Z]{3})/');

        if (isset($currency)) {
            $it['Currency'] = $currency;
        }

        // Fees
        $it['Fees'] = [];
        $totaltTax = 0.0;
        $values = $this->http->XPath->query('//div[@id="reservations_controls_reservationdetails_ascx1_BookItPanel"]//tr/td[2]');

        if ($values->length > 3) {
            $low = 0;
            $top = $values->length - 2;

            for ($i = $low; $i < $top; $i++) {
                $name = $values->item($i)->nodeValue;
                $value = $this->http->XPath->query('following::td[1]', $values->item($i));

                if ($value->length > 0) {
                    if (preg_match('/fee/ims', $name) || preg_match('/SCHG/ims', $name) || preg_match('/SRG/ims', $name)) { //fee
                        $this->logger->debug("fee {$name}");
                        $nameFee = preg_replace('/\([^\)]*\)/ims', '', $name);
                        $this->logger->debug($nameFee);
                        $this->logger->debug($value->item(0)->nodeValue);

                        if (preg_match('/(\d+.\d+|\d+)/ims', $value->item(0)->nodeValue, $matches)) {
                            $it['Fees'][] = ['Name' => trim($nameFee), 'Charge' => $matches[1]];
                        }
                    } else { //tax
                        $this->http->Log("tax");
                        $this->http->Log("$name");

                        if (preg_match('/tax/ims', $name)) {
                            $this->http->Log($value->item(0)->nodeValue);

                            if (preg_match('/(\d+.\d+|\d+)/ims', $value->item(0)->nodeValue, $matches)) {
                                $totaltTax += floatval(trim($matches[1]));
                            }// if (preg_match('/(\d+.\d+|\d+)/ims', $value->item(0)->nodeValue, $matches))
                        }// if (preg_match('/tax/ims', $name))
                    }
                }// if ($value->length > 0)
            }// for ($i = $low; $i < $top; $i++)
        }// if ($values->length > 3)
        $it['TotalTaxAmount'] = $totaltTax;

        // Priced Equips
        //		$it['PricedEquips'] = array();
        //		$names = $this->http->XPath->query('//div[@id="reservations_controls_reservationdetails_ascx1_OptionsPanel"]//tr/td[2]');
        //		$values = $this->http->XPath->query('//div[@id="reservations_controls_reservationdetails_ascx1_OptionsPanel"]//tr/td[3]');
        //		if($names->length > 0 && $values->length > 0) {
        //			$top = $names->length - 1;
        //			for($i = 0; $i < $top; $i++) {
        //				$clearValue = $values->item($i)->nodeValue;
        //				if(preg_match('/([^\d]+)((\d|\.)*)$/ims', $clearValue, $matches))
        //					$clearValue = $matches[2];
        //				$it['PricedEquips'][] = array('Name' =>trim($names->item($i)->nodeValue), "Charge" => $clearValue);
        //			}
        //		}

        // Discounts
        $it['Discount'] = $this->http->FindSingleNode("//td[span[contains(text(), 'DISCOUNT')]]/following-sibling::td[1]/span", null, true, '/(\d+.\d+|\d+)/');
        //		$it['Discounts'] = array();
        //		$discount = $this->http->FindSingleNode("//*[contains(@id, 'ChargesRepeater')]");
//        if (isset($discount) && $discount != 'Not Available') {
        //			$it['Discounts'][] = array("Name" => $discount , "Code" => "Corporate Discount Number");
        //		}// if (isset($discount) && $discount != 'Not Available')
        //		if($this->http->ParseForm('Form1')) {
        //			$this->http->Form['reservations_controls_reservationdetails_ascx1$ChangeLocationButton.x'] = '1';
        //			$this->http->Form['reservations_controls_reservationdetails_ascx1$ChangeLocationButton.y'] = '1';
        //			$this->http->PostForm();
        //			$promo = $this->http->XPath->query('//span[contains(text(), "Promo #")]/following::span[1]');
        //			if($promo->length > 1)
        //				$it['Discounts'][] = array("Name" => $promo->item($promo->length - 1)->nodeValue , "Code" => "Promo #");
        //		}

        return $it;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.thriftycars4rent.com/#ManageReservation";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->getURL($this->ConfirmationNumberURL($arFields));
        $this->http->ParseForm('reservation-form');
        $this->http->SetInputValue('nw_reservation-number', $arFields["ConfNo"]);
        $this->http->SetInputValue('nw_last-name', $arFields["LastName"]);

        if (!$this->http->PostForm()) {
            return null;
        }
        $error = $this->http->FindSingleNode('//div[contains(@style,"block;") and contains(text(), "Reservation Not Found.")]');

        if (isset($error)) {
            return $error;
        }

        return null;
        // skip login form
        if ($this->http->ParseForm("Form1") && $this->http->FindPreg("/Keep\s*my\s*Blue\s*Chip\s*Number\,\s*but\s*don\'t\s*Login/ims")) {
            $this->http->Form['__EVENTTARGET'] = 'reservations_controls_bluechippassword_ascx1$ContinueWithoutBlueChipLoginLinkButton';

            if (!$this->http->PostForm()) {
                return $this->notifications($arFields);
            }
        }

        if ($msg = $this->http->FindSingleNode("//p[@class='ErrorAlert'][contains(.,'Reservation not on file')]/span[@id][1]")) {
            return $msg;
        }
        $it = $this->ParseConfirmationThrifty();
        $it = [$it];

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"             => "PostingDate",
            "Rental Agreement" => "Info",
            "Description"      => "Description",
            "Points"           => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->http->FilterHTML = false;
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->http->GetURL('https://www.thrifty.com/bluechip/index.aspx');

        if ($message = $this->http->FindPreg("/No rental agreements found./ims")) {
            $this->logger->notice(">>>> " . $message);

            return $result;
        }
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParseHistoryPage($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query('//div[contains(@id, "MainMemberLoggedIn_RewardsMemberModule_lineItem")]/div[@class = "lineItem"]');
        $this->logger->debug("Total {$nodes->length} history items");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $dateStr =
                $this->http->FindSingleNode('div[@id = "clickableArrows"]/span[@class = "date"]', $node)
                ?? $this->http->FindSingleNode('div[@id = "clickableArrows"]/text()[last()]', $node)
            ;
            $postDate = strtotime($dateStr);

            if ((isset($startDate) && $postDate < $startDate) || $dateStr == '') {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $this->http->FindSingleNode('div[@class = "description"]', $nodes->item($i));
            $result[$startIndex]['Rental Agreement'] = $this->http->FindSingleNode('div[@class = "rentalAgreement"]', $nodes->item($i));
            $result[$startIndex]['Points'] = $this->http->FindSingleNode('div[@class = "points"]', $nodes->item($i));

            $startIndex++;
        }// for ($i = 0; $i < $nodes->length; $i++)

        return $result;
    }
}
