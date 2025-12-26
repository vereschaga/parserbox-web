<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerWoodfield extends TAccountChecker
{
    use ProxyList;

    protected $endHistory = false;

    private $token = null;
    private $apikey = "NjAwQjY2QjEtQzJCQy00ODdFLTk2MzYtQTQ1MUVGODkxMEYz";
    private $memberNumber = "";
    private $firstName = "";
    private $lastName = "";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setDefaultHeader("User-Agent", urlencode("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/604.5.6 (KHTML, like Gecko) Version/11.0.3 Safari/604.5.6"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.lq.com/en/account/account-summary";

        return $arg;
    }

    public function LoadLoginForm()
    {
        if (empty($this->AccountFields['Login2'])) {
            throw new CheckException("To update this La Quinta, etc. (Returns) account you need to fill in the 'Last Name' field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        // set cookies
        $this->http->setCookie("global.locale", "en", ".lq.com");
        $this->http->setCookie("tcookie", "true", ".lq.com");

        $this->http->GetURL("https://www.lq.com/en/account/account-summary");

        $email = "";
        $returnsId = "";

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            $email = $this->AccountFields['Login'];
        } else {
            $returnsId = $this->AccountFields['Login'];
        }

        $data = [
            "LoyaltyServiceAuthenticateRequest" => [
                "email"     => $email,
                "lastName"  => $this->AccountFields['Login2'],
                "returnsId" => $returnsId,
                "password"  => $this->AccountFields['Pass'],
            ],
        ];
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json;charset=utf-8",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.lqwebservices.com/lqecommerce/api/ecommerce/loyalty/profiles/authenticate?apikey={$this->apikey}", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        // Network error 28
        if (strstr($this->http->Error, 'Network error 28 - Operation timed out after')) {
            throw new CheckRetryNeededException(3);
        }
//        $this->http->SetInputValue('returnsid', $this->AccountFields['Login']);
//        $this->http->SetInputValue('lastNameLogin', $this->AccountFields['Login2']);
//        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
//        $this->http->SetInputValue('remember', "true");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are in the process of upgrading the site
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")) {
            throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->token)) {
            $this->token = $response->token;
            $this->http->setDefaultHeader("Lq_auth_key", $this->token);
            $this->http->setDefaultHeader("Accept", "application/json, text/plain, */*");

            return true;
        }
        // Returns account cannot be accessed. Please call 1-800-642-4271 for assistance.
        if ($message = $this->http->FindPreg("/^(The email address or member number and password combination you entered is invalid, please retry or click on the \"Password\?\" link to reset your password\.)\s*$/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please enter a valid email address.
        if ($message = $this->http->FindPreg("/^(Please enter a valid email address\.)\s*$/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Returns account cannot be accessed. Please call 1-800-642-4271 for assistance\.
        if ($message = $this->http->FindPreg("/^(Returns account cannot be accessed. Please call 1-800-642-4271 for assistance\.)$/ims")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // We're not able to retrieve profile information at this time. Please call 1-800-642-4271 for assistance.
        if ($message = $this->http->FindPreg("/^(We're not able to retrieve profile information at this time.+?)\.$/is")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The email address you provided is used within multiple La Quinta Returns accounts. Please call 1-800-642-4258 for assistance.
        if ($message = $this->http->FindPreg("/^The email address you provided is used within multiple La Quinta Returns accounts\. Please.+?\.$/i")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.lqwebservices.com/lqecommerce/api/ecommerce/loyalty/profiles?apikey={$this->apikey}");
        $profile = $this->http->JsonLog();

        // Balance - Current Point Balance
        if (isset($profile->pointsSummary->currentPointsBalance)) {
            $this->SetBalance($profile->pointsSummary->currentPointsBalance);
        } else {
            $this->logger->notice("Balance not found");
        }
        // Name
        if (isset($profile->firstName, $profile->lastName, $profile->middleInitial)) {
            $this->SetProperty('Name', beautifulName(CleanXMLValue("$profile->firstName $profile->lastName  $profile->middleInitial")));
            // for itineraries
            $this->firstName = $profile->firstName;
            $this->lastName = $profile->lastName;
        } else {
            $this->logger->notice("Name not found");
        }
        // Status
        if (isset($profile->returnsTier)) {
            $this->SetProperty('MemberStatus', $profile->returnsTier);
        } else {
            $this->logger->notice("MemberStatus not found");
        }
        // Member Number
        if (isset($profile->returnsNumber->id)) {
            $this->SetProperty('MemberNumber', $profile->returnsNumber->id);
            $this->memberNumber = $profile->returnsNumber->id;
        } else {
            $this->logger->notice("MemberNumber not found");
        }
        // Nights Needed to Reach Next Member Level
        if (isset($profile->pointsSummary->nightsToNextMemberLevel)) {
            $this->SetProperty('NightNeeded', $profile->pointsSummary->nightsToNextMemberLevel);
        } else {
            $this->logger->notice("NightNeeded not found");
        }
        // Account Adjustments
        if (isset($profile->pointsSummary->accountAdjustments)) {
            $this->SetProperty('AccountAdjustments', $profile->pointsSummary->accountAdjustments);
        } else {
            $this->logger->notice("AccountAdjustments not found");
        }
        // YTD Points Earned
        if (isset($profile->pointsSummary->pointsEarnedYTD)) {
            $this->SetProperty('YTDPointsEarned', $profile->pointsSummary->pointsEarnedYTD);
        } else {
            $this->logger->notice("YTDPointsEarned not found");
        }
        // YTD Bonus Points
        if (isset($profile->pointsSummary->bonusPointsYTD)) {
            $this->SetProperty('YTDBonusPoints', $profile->pointsSummary->bonusPointsYTD);
        } else {
            $this->logger->notice("YTDBonusPoints not found");
        }
        // YTD Points Redeemed
        if (isset($profile->pointsSummary->pointsRedeemedYTD)) {
            $this->SetProperty('YTDPointsRedeemed', $profile->pointsSummary->pointsRedeemedYTD);
        } else {
            $this->logger->notice("YTDPointsRedeemed not found");
        }

        // Expiration Date
//        $this->http->GetURL("https://www.lq.com/en/account/account-summary");
        $this->http->GetURL("https://www.lqwebservices.com/lqecommerce/api/ecommerce/loyalty/profiles/activities?apikey={$this->apikey}");
        $result = $this->http->JsonLog(null, false);
        $this->logger->debug("Total " . (is_array($result) ? count($result) : "none") . " transactions were found");
        // Search all dates witch balance
        if ($result) {
            foreach ($result as $row) {
                // If there are points
                if (in_array($row->type, ["ORDERED", "EARNED"])) {
                    if (isset($row->earnedRewardType) && $row->earnedRewardType == 'BONUS') {
                        $this->logger->notice("Skip Bonus");

                        continue;
                    } elseif (isset($row->earnedRewardType)) {
                        $this->logger->debug("Type -> $row->earnedRewardType");
                    } else {
                        $this->logger->debug("Type -> Unknown");
                    }
                }
                $expire[] = [
                    'date'   => $row->date,
                    'points' => $row->points,
                ];
            }
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (isset($expire)) {
            // Log
            $this->logger->debug(var_export($expire, true), ["pre" => true]);
            // Find the nearest date with non-zero balance
            $N = count($expire);
            $i = $N - 1;

            while (($i >= 0)) {
                if ($date = strtotime($expire[$i]['date'])) {
                    $this->logger->debug("Last Activity $date - " . var_export($expire[$i]['date'], true));
                    //# Last Activity
                    $this->SetProperty('LastActivity', $expire[$i]['date']);
                    //# Expiration Date
                    $this->logger->debug("Expiration Date - " . var_export(date("m-d-Y", strtotime("+18 month", $date)), true));
                    $this->SetExpirationDate(strtotime("+18 month", $date));

                    break;
                }// if ($date = strtotime($expire[$i]['date']))
                $i--;
            }// while (($i >= 0 ))
        }// if (isset($expire))
        else {
            $this->logger->notice("woodfield - refs #4171, #7460. LastActivity not found");
        }

        // Alternative Expiration Date // refs #4171, #7460
        if (!isset($this->Properties['LastActivity'])) {
//            $this->http->GetURL("https://www.lq.com/lq/respath/changecancelsearch.do?action=OPEN&mode=view");
            //# Get All Reservations
            $nodes = $this->http->XPath->query("//table[@class = 'viewResTable']//tr[td[contains(@class, 'confNumber')]]");
            $this->logger->debug("Total nodes found: " . $nodes->length);

            for ($i = 0; $i < $nodes->length; $i++) {
                $node = $nodes->item($i);
                //# If there are points
                $status = strtolower(CleanXMLValue($this->http->FindSingleNode("td[5]", $node)));

                if ($this->http->FindSingleNode("td[2]", $node) != ''
                    && in_array($status, ['active', 'checked out'])) {
                    $expire[] = [
                        'date'    => $this->http->FindSingleNode("td[2]", $node),
                        'status'  => $this->http->FindSingleNode("td[5]", $node),
                    ];
                }
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (isset($expire)) {
                // Log
                $this->http->Log(var_export($expire, true), true);
                // Find the nearest date with non-zero balance
                $N = count($expire);
                $i = 0;

                while (($i < $N)) {
                    if ($date = strtotime($expire[$i]['date'])) {
                        $this->logger->debug("Last Activity $date - " . var_export($expire[$i]['date'], true));
                        //# Last Activity
                        $this->SetProperty('LastActivity', $expire[$i]['date']);
                        //# Expiration Date
                        $this->logger->debug("Expiration Date - " . var_export(date("m/d/Y", strtotime("+18 month", $date)), true));
                        $this->SetExpirationDate(strtotime("+18 month", $date));

                        break;
                    }
                    $i++;
                }
            }// if (isset($expire))
        }// Alternative Expiration Date
    }

    public function ParseItineraries()
    {
        $this->http->FilterHTML = false;
        $result = [];
        $endDate = date("Y-m-d", strtotime("+1 year"));

        if ($this->ParsePastIts) {
            $startDate = date("Y-m-d", strtotime("-1 year"));
        } else {
            $startDate = date("Y-m-d");
        }

        if (!empty($this->memberNumber)) {
            $this->http->GetURL("https://www.lqwebservices.com/lqecommerce/api/ecommerce/centralreservation/reservations/{$this->memberNumber}?apikey={$this->apikey}&endDate={$endDate}&startDate={$startDate}");
        } else {
            $this->sendNotification("woodfield. Something went wrong");
        }
        //			$this->http->GetURL("https://www.lq.com/en/my-reservations.displayReservations.html?jsonReturn=true&sessionId={$this->token}&returnsId=" . $this->AccountFields['Login']);//todo
        $nodes = $this->http->JsonLog(null, false);

        if (!$nodes) {
            $this->logger->notice("No results");
            // NoItineraries
            if ($this->http->FindPreg("/^\[\]$/")) {
                return $this->noItinerariesArr();
            }

            return $result;
        }
        $res = [];
        $this->logger->debug("Total " . count($nodes) . " reservations were found");

        foreach ($nodes as $reservation) {
            $arr = ['Kind' => 'R'];
            $arr['ConfirmationNumber'] = $reservation->confirmationNumber->number;

            if (intval($arr['ConfirmationNumber']) == 0) {
                continue;
            }
            $arr['CheckInDate'] = strtotime($reservation->arrivalDate);
            $arr['CheckOutDate'] = strtotime($reservation->departureDate);

            if ($arr['CheckOutDate'] < time() && !$this->ParsePastIts) {
                $this->logger->notice("Skip old reservation {$arr['ConfirmationNumber']}");

                continue;
            }
            $arr['HotelName'] = $reservation->hotelSummary->name;
            // Status
            $status = $reservation->status->id;

            if (strtoupper($status) == 'CANCELLED') {
                $arr['Cancelled'] = true;
                unset($arr['CheckInDate']);
                unset($arr['CheckOutDate']);
                unset($arr['HotelName']);
            } else {
                $arr['href'] = "https://www.lqwebservices.com/lqecommerce/api/ecommerce/centralreservation/reservations?apikey={$this->apikey}&confirmationNumber={$arr['ConfirmationNumber']}&firstName={$this->firstName}&lastName={$this->lastName}";
            }

            if (!empty($arr)) {
                $res[] = $arr;
            }
        }
        $this->logger->debug("Total " . count($res) . " reservations were parsed!");

        foreach ($res as $arr) {
            $this->logger->info('Parse itinerary #' . $arr['ConfirmationNumber'], ['Header' => 3]);

            if (isset($arr['href'])) {
                $this->http->GetURL($arr['href']);
                $arr = $this->ParseItinerary();
            }

            if (!empty($arr)) {
                $result[] = $arr;
            }

            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($arr, true), ['pre' => true]);
        }// foreach ($res as $arr)

        return $result;
    }

    public function ParseItinerary()
    {
        /** @var \AwardWallet\ItineraryArrays\Hotel $result */
        $res = $this->http->JsonLog(null, false);

        if (!isset($res[0])) {
            $this->logger->error("Reservation Json not found");

            return false;
        }
        $res = $res[0];
        $result['Kind'] = 'R';
        $reservation = $res->reservation;
        $result['ConfirmationNumber'] = $reservation->confirmationNumber->number;
        $result['Status'] = $reservation->status->id;
        $hotelSummary = $res->hotelSummary;
        $result['HotelName'] = $hotelSummary->name;

        $address = $hotelSummary->address;
        $result["DetailedAddress"] = [[
            "AddressLine" => trim($address->street . ' ' . $address->street2),
            "CityName"    => $address->city,
            "PostalCode"  => $address->postalCode,
            "StateProv"   => $address->stateProvince,
            "Country"     => $address->countryCode,
        ]];
        $result['Address'] = implode(', ', $result["DetailedAddress"][0]);
        $result['Phone'] = (!empty($hotelSummary->primaryPhoneNumber->areaCode)) ? $hotelSummary->primaryPhoneNumber->areaCode . "-" . $hotelSummary->primaryPhoneNumber->number : $hotelSummary->primaryPhoneNumber->number;
        $result['Fax'] = (!empty($hotelSummary->phoneNumbers->FAX->phoneNumber->areaCode)) ? $hotelSummary->phoneNumbers->FAX->phoneNumber->areaCode . "-" . $hotelSummary->phoneNumbers->FAX->phoneNumber->number : $hotelSummary->phoneNumbers->FAX->phoneNumber->number;
        $result['EarnedAwards'] = $res->pointsEarned;

        if ($reservationDate = strtotime($reservation->createdDate)) {
            $result['ReservationDate'] = $reservationDate;
        }
        $result['EarnedAwards'] = $res->pointsEarned;

        // get Hotel info
        $this->http->GetURL("https://www.lqwebservices.com/lqecommerce/api/ecommerce/propertymaster/hotels?apikey={$this->apikey}&id={$hotelSummary->innNumber->id}");
        $propertymaster = $this->http->JsonLog(null, false);

        $timeIn = $propertymaster[0]->checkInTime;
        $dateIn = $reservation->arrivalDate;

        if (isset($dateIn) && isset($timeIn)) {
            $result['CheckInDate'] = strtotime($dateIn . " " . $timeIn);
        }
        $timeOut = $propertymaster[0]->checkOutTime;
        $dateOut = $reservation->departureDate;

        if (isset($timeOut) && isset($dateOut)) {
            $result['CheckOutDate'] = strtotime($dateOut . " " . $timeOut);
        }
        $result['GuestNames'][] = beautifulName($reservation->mainContact->firstName . " " . $reservation->mainContact->lastName);
        $result['AccountNumbers'][] = $reservation->mainContact->returnsNumber->id;
        $result['RateType'] = $reservation->rateQualifier->name;
        $result['Guests'] = $reservation->roomReservations[0]->numberOfAdults;
        $result['Kids'] = $reservation->roomReservations[0]->numberOfChildren;
        $result['Rooms'] = $reservation->roomReservations[0]->numberOfRooms;
        $result['RoomType'] = $reservation->roomReservations[0]->roomType->roomDescription;
        $result['Cost'] = $reservation->quotedTotalRate->amount ?? null;
        $result['Total'] = $reservation->quotedTotalRateWithTaxes->amount;
        $result['Currency'] = $reservation->quotedTotalRateWithTaxes->currencyCode;

        return $result;
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
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
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
        return "https://www.lq.com/en";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
//        $this->http->GetURL( $this->ConfirmationNumberURL($arFields) );
        $this->http->GetURL("https://www.lqwebservices.com/lqecommerce/api/ecommerce/centralreservation/reservations?apikey={$this->apikey}&confirmationNumber={$arFields['ConfNo']}&firstName={$arFields['FirstName']}&lastName={$arFields['LastName']}");
        $res = $this->ParseItinerary();

        if (!empty($res)) {
            $it[] = $res;
        } elseif ($error = $this->http->FindPreg("/^(?:We're not able to retrieve your reservations based on a guest name mismatch with your account. Please call 1-800-642-4258 for assistance\.|Please enter a valid 10-digit reservation confirmation number to retrieve your reservation\(s\)\.)$/")) {
            return $error;
        } else {
            $this->sendNotification("woodfield - failed to retrieve itinerary by conf #", 'all', true,
                "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['FirstName']} / {$arFields['LastName']}");
        }

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"              => "PostingDate",
            "Inn #"             => "Info",
            "Description"       => "Description",
            "Nights"            => "Info",
            "QTY/Certificate #" => "Info",
            "Points"            => "Miles",
            "Bonus"             => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->http->GetURL("https://www.lqwebservices.com/lqecommerce/api/ecommerce/loyalty/profiles/activities?apikey={$this->apikey}");
        $page = 0;
//        do {
//            $page++;
        $this->logger->debug("[Page: {$page}]");
//            if ($page > 1 && isset($url)) {
//                $this->http->NormalizeURL($url);
//                $this->http->GetURL($url);
//            }
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate));
//            if ($page > 30) {
//                $this->http->Log("too many pages");
//                break;
//            }
//        } while (
//            ($url = $this->http->FindSingleNode("//a[contains(text(), 'Next')]/@href"))
//            && !$this->endHistory
//        );

        usort($result, function ($a, $b) { return $b['Date'] - $a['Date']; });

        $this->getTime($startTimer);

        return $result;
    }

    public function ParseHistoryPage($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, false);
        // Search all dates witch balance
        if ($response) {
            $this->logger->debug("Total " . count($response) . " transactions were found");

            foreach ($response as $row) {
                $dateStr = $row->date;
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");
                    $this->endHistory = true;

                    continue;
                }// if (isset($startDate) && $postDate < $startDate)
                $result[$startIndex]['Date'] = $postDate;
                $result[$startIndex]['Inn #'] = (isset($row->innNumber->id)) ? $row->innNumber->id : '';
                $result[$startIndex]['Description'] = $row->description;
                $result[$startIndex]['Nights'] = $row->nights ?? '  ';
                $result[$startIndex]['QTY/Certificate #'] = $row->quantity;

                if (isset($row->earnedRewardType) && $row->earnedRewardType == 'BONUS') {
                    $result[$startIndex]['Bonus'] = $row->points;
                } else {
                    $result[$startIndex]['Points'] = $row->points;
                }
                $startIndex++;
            }// foreach ($response->results as $row)
        }// if (isset($response->results))

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }
}
