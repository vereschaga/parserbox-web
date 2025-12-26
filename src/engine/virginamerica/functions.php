<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerVirginamerica extends TAccountChecker
{
    use ProxyList;

    public $history = [];
    public $itineraries = null;
    public $noItineraries = false;

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        }
        $this->http->LogHeaders = true;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && (false !== strpos($properties['SubAccountCode'], 'virginamerica_travelbank'))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
//        $arg['CookieURL'] = 'https://www.virginamerica.com/api/v0/cart/retrieve';
        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // filter by login
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false
            && (!is_numeric($this->AccountFields['Login']) || $this->http->FindPreg("/^\d+\.$/", false, $this->AccountFields['Login']))) {
            throw new CheckException('Invalid Email or Elevate #', ACCOUNT_INVALID_PASSWORD);
        }

        // retries
        if (!$this->http->GetURL("https://www.virginamerica.com/elevate-frequent-flyer") && $this->http->Response['code'] == 0) {
            throw new CheckRetryNeededException(3, 20);
        }
        // cookies
        $this->http->GetURL("https://www.virginamerica.com/api/v0/cart/retrieve");
        // headers
        $this->http->setDefaultHeader('Content-Type', 'application/json;charset=utf-8');

        if (strstr($this->AccountFields['Login'], '@')) {
            $login = 'userId';
        } else {
            $login = 'elevateId';
        }
        $this->http->PostURL("https://www.virginamerica.com/api/v0/elevate/login", '{"' . $login . '":"' . trim($this->AccountFields['Login']) . '","password":"' . str_replace('"', '\"', $this->AccountFields['Pass']) . '"}');

        if ($this->http->Response['code'] === 0) {
            throw new CheckRetryNeededException(2, 10);
        }

        return true;
    }

    public function checkErrors()
    {
        // An error occurred while processing your request
        if ($this->http->FindPreg("/An error occurred while processing your request/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->status->status) && $response->status->status == 'SUCCESS') {
            return true;
        }
        // Oops. The password or the user ID isn't correct. Please check and try again.
        elseif (isset($response->status->error->code)) {
            switch ($response->status->error->code) {
                case '2713661':
                case '8882730002':case '2703183':
                    throw new CheckException("Oops. The password or the user ID isn't correct. Please check and try again.", ACCOUNT_INVALID_PASSWORD);

                    break;

                case '2700000':case '91156000':case '8882730000': case '2718963': case '2713906':
                    throw new CheckException("Whoa there, something went wrong. Please call 1.877.FLY.VIRGIN.", ACCOUNT_PROVIDER_ERROR);

                    break;

                case '91151069':case '2713666': case '2718401': case '91151035': case '2718492':
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

                    break;

                default:
                    $this->logger->error("Unknown error");
            }// switch ($response->status->error->code)
        }// elseif (isset($response->status->error->code))

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = json_decode($this->http->Response['body']);
        $this->http->Log("<pre>" . var_export($response, true) . "</pre>", false);
        // Name
        if (isset($response->response->firstName, $response->response->lastName)) {
            $this->SetProperty("Name", beautifulName(CleanXMLValue($response->response->firstName . " " . $response->response->lastName)));
        } else {
            $this->logger->notice("Name is not found");
        }
        // Retired Elevate #
        if (isset($response->response->elevateId)) {
            $this->SetProperty("Number", $response->response->elevateId);
        } else {
            $this->logger->notice("Number is not found");
        }
        // Elevate Status
        if (isset($response->response->tierLevel)) {
            $this->SetProperty('CurrentElevateStatus', $response->response->tierLevel);
        } else {
            $this->logger->notice("CurrentElevateStatus is not found");
        }
        // Status Points
        if (isset($response->response->numOfTierQualifyingPoints)) {
            $this->SetProperty('StatusPoints', $response->response->numOfTierQualifyingPoints);
        } else {
            $this->logger->notice("StatusPoints is not found");
        }
        // Points To Next Tier
        if (isset($response->response->numOfPointsToNextTier)) {
            $this->SetProperty('PointsToNextTier', $response->response->numOfPointsToNextTier);
        } else {
            $this->logger->notice("PointsToNextTier is not found");
        }

        // Load account info
        if (isset($response->response->authToken, $response->response->elevateId)) {
            $authToken = $response->response->authToken;
            $this->http->setDefaultHeader('Content-Type', 'application/json;charset=utf-8');
            $this->http->PostURL("https://www.virginamerica.com/api/v0/elevate/retrieve-account-info", '{"futureBookingDetail":true,"searchPointsByDurationType":"ALL_DATES","searchPointsByStatusType":"ALL_POINTS","sortOrderDirectionType":"SORT_DESCENDING","sortOrderType":"COL_DATE","elevateId":"' . $response->response->elevateId . '","authToken":"' . $authToken . '"}');
            $response = $this->http->JsonLog(null, false);

            // for Itineraries
            $this->itineraries = $response;

//            $this->logger->debug(var_export($response, true), ["pre" => true]);
            // Balance - Alaska Mileage Plan Miles
            if (isset($response->response->elevateAccount->mpMilesBalance)) {
                $this->SetBalance($response->response->elevateAccount->mpMilesBalance);
            }
            // AccountID: 3394002, 614578
            elseif (isset($response->response->elevateAccount->mpAcctStatus, $response->response->elevateAccount->pointsAvailable)
                && $response->response->elevateAccount->mpAcctStatus == 0
                // && $response->response->elevateAccount->pointsAvailable == 0 // AccountID: 2571289
                && !isset($response->response->elevateAccount->mpAcctID)) {
                $this->SetBalance(0);
            }
            // AccountID: 2770942
            elseif (isset($response->response->elevateAccount->pointsAvailable)
                && $response->response->elevateAccount->pointsAvailable <= 0
                && !isset($response->response->elevateAccount->mpAcctID)) {
                $this->SetBalanceNA();
            }

            // Mileage Plan #
            if (isset($response->response->elevateAccount->mpAcctID)) {
                $this->SetProperty("AccountNumber", $response->response->elevateAccount->mpAcctID);
            } else {
                $this->logger->notice("AccountNumber is not found");
            }
            // Elevate Status
            if (isset($response->response->tierDetail->tierLevel)) {
                $this->SetProperty('CurrentElevateStatus', $response->response->tierDetail->tierLevel);
            } else {
                $this->logger->notice("CurrentElevateStatus is not found");
            }
            // Status Points
            if (isset($response->response->tierDetail->tierQualifyingPoint)) {
                $this->SetProperty('StatusPoints', $response->response->tierDetail->tierQualifyingPoint);
            } else {
                $this->logger->notice("StatusPoints is not found");
            }
            // Points To Next Tier
            if (isset($response->response->tierDetail->pointsToNextTier)) {
                $this->SetProperty('PointsToNextTier', $response->response->tierDetail->pointsToNextTier);
            } else {
                $this->logger->notice("PointsToNextTier is not found");
            }
            // Renewal Date
            if (isset($response->response->elevateAccount->renewalDate)) {
                $this->SetProperty('RenewalDate', preg_replace("/T.+/ims", "", $response->response->elevateAccount->renewalDate));
            } else {
                $this->logger->notice("RenewalDate is not found");
            }
            // Member Since
            if (isset($response->response->elevateAccount->creationDateTime)) {
                $this->SetProperty('MemberSince', preg_replace("/T.+/ims", "", $response->response->elevateAccount->creationDateTime));
            } else {
                $this->http->Log("MemberSince is not found");
            }
            // Expiration Date
//            if (isset($response->response->elevateAccount->expirationDate)) {
//                $expireDate = preg_replace("/T.+/ims", "", $response->response->elevateAccount->expirationDate);
//                if (strtotime($expireDate))
//                    $this->SetExpirationDate(strtotime($expireDate));
//                else
//                    $this->logger->notice("Expiration Date is {$expireDate}");
//            }
//            else
//                $this->logger->notice("Expiration Date is not found");
            // Last Activity
            if (isset($response->response->pointDetailList[0]->activityDateTime)) {
                $this->SetProperty("LastActivity", preg_replace("/T.+/ims", "", $response->response->pointDetailList[0]->activityDateTime));
            } else {
                $this->logger->notice("Last Activity is not found");
            }
            // history
            if (isset($response->response->pointDetailList)) {
                $this->history = $response->response->pointDetailList;
            }

            // Travel Bank
            $this->http->setDefaultHeader('Content-Type', 'application/json;charset=utf-8');
            $this->http->PostURL("https://www.virginamerica.com/api/v0/elevate/travel-bank-balance-by-authtoken", '{"authToken":"' . $authToken . '"}');
            $response = json_decode($this->http->Response['body']);
            $this->logger->debug(var_export($response, true), ["pre" => true]);

            if (isset($response->response->balance)) {
                //$this->SetProperty('TravelBank', '$' . $response->response->balance);
                $subAccounts = [];
                $subAccounts[] = [
                    'Code'        => 'virginamerica_travelbank',
                    'DisplayName' => 'Travel Bank',
                    'Balance'     => $response->response->balance,
                    'Currency'    => 'USD',
                ];
                $this->SetProperty('CombineSubAccounts', false);
                $this->SetProperty('SubAccounts', $subAccounts);
            } else {
                $this->logger->notice("Travel Bank is not found");
            }
        } else {
            $this->logger->notice("authToken is not found");
        }
    }

    public function ParseItineraries()
    {
        $result = [];
//        $this->http->GetURL("https://www.virginamerica.com/manage-itinerary");
//        $response = json_decode($this->http->Response['body']);
//        $this->logger->debug(var_export($response, true), ["pre" => true]);
        $response = $this->itineraries;
        // Round Trip
        if (isset($response->response->manageFlightDetail->firstPnrInfo)) {
            $this->logger->notice('Found Round Trip');
            $result[] = $this->ParseItinerary($response->response->manageFlightDetail->firstPnrInfo);
        }
        // Round Trip v.2
        if (isset($response->response->manageFlightDetail->pnrList)) {
            $this->logger->notice('Found Round Trip v.2');

            foreach ($response->response->manageFlightDetail->pnrList as $itinerary) {
                $parsedItinerary = $this->parseRoundTripItinerary($itinerary);

                if (!$this->findSimilarItinerary($result, $parsedItinerary)) {
                    $result[] = $parsedItinerary;
                }
            }// foreach ($seg->flightSegList as $flightSeg)
        }
        unset($itinerary);
        // List of itineraries
        if (!isset($response->response->checkingPNRList)) {
            return $result;
        }
        $this->logger->debug('Found ' . count($response->response->checkingPNRList) . ' Reservations');

        foreach ($response->response->checkingPNRList as $itinerary) {
            $this->logger->debug(var_export($itinerary, true), ["pre" => true]);
            $parsedItinerary = $this->ParseItinerary($itinerary);

            if (!$this->findSimilarItinerary($result, $parsedItinerary)) {
                $result[] = $parsedItinerary;
            }
        }

        return $result;
    }

    public function parseSegment($seg, $itinerary)
    {
        $this->logger->notice(__METHOD__);
        // FlightNumber
        $segment['FlightNumber'] = $seg->flightSeg->flightNum;
        // Aircraft
        $segment['Aircraft'] = ($seg->flightSeg->aircraftType != 'NOT_SUPPORTED') ? $seg->flightSeg->aircraftType : '';
        // Cabin
        $cabinTypes = [
            'MC'  => 'Main Cabin',
            'MCS' => 'Main Cabin Select',
            'FC'  => 'First Class',
        ];
        $ct = $seg->flightSeg->cabinType;
        $segment['Cabin'] = $cabinTypes[$ct] ?? null;
        // DepCode, DepName
        $segment['DepCode'] = $segment['DepName'] = $seg->flightSeg->departure;

        if (is_array($segment['DepCode'])) {
            $this->sendNotification("virginamerica. " . var_export($segment['DepCode'], true));
        }
        // DepDate
        $depD = explode('T', $seg->flightSeg->departureDateTime);
        $depDate = $depD[0];
        $depTime = preg_replace('/-.+/', '', $depD[1]);
        $this->logger->debug("DepDate: $depDate $depTime");
        $depDate = strtotime($depDate . ' ' . $depTime);
        // ISO 8601
        // may be it is better?
//        $depDate = strtotime($seg->flightSeg->departureDateTime);
        if ($depDate) {
            $segment['DepDate'] = $depDate;
        }
        // ArrCode, ArrName
        $segment['ArrCode'] = $segment['ArrName'] = $seg->flightSeg->arrival;

        if (is_array($segment['ArrCode'])) {
            $this->sendNotification("virginamerica. " . var_export($segment['ArrCode'], true));
        }
        // ArrDate
        $arrD = explode('T', $seg->flightSeg->arrivalDateTime);
        $arrDate = $arrD[0];
        $arrTime = preg_replace('/-.+/', '', $arrD[1]);
        $this->logger->debug("ArrDate: $arrDate $arrTime");
        $arrDate = strtotime($arrDate . ' ' . $arrTime);
        // ISO 8601
        // may be it is better?
//        $arrDate = strtotime($seg->flightSeg->arrivalDateTime);
        if ($arrDate) {
            $segment['ArrDate'] = $arrDate;
        }
        // Passengers
        if (isset($seg->flightSeg->segACSInfo->paxListInfo)) {
            foreach ($seg->flightSeg->segACSInfo->paxListInfo as $passenger) {
                $segment['Passengers'][] = beautifulName($passenger->firstName . ' ' . $passenger->lastName);
            }
        }
        // Seats
        if (isset($itinerary->seatInfoList)) {
            foreach ($itinerary->seatInfoList as $seat) {
                if ($segment['FlightNumber'] == $seat->flightNum) {
                    $seats[] = $seat->seat->seatNumber;
                }
            }
        }

        if (isset($seats)) {
            $segment['Seats'] = implode(', ', $seats);
        }
        // Stops
//        $segment['Stops'] = $this->http->FindSingleNode("tr[5]/td[3]/div", $nodeValue, true, '/Stops:\s(.+)/ims');

        return $segment;
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
        return "https://www.virginamerica.com/flight-check-in";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        //		$this->http->GetURL( $this->ConfirmationNumberURL($arFields) );
        $this->http->setDefaultHeader('Content-Type', 'application/json;charset=utf-8');
        $this->http->PostURL("https://www.virginamerica.com/api/v0/manage/pnr", '{"identificationInfo":{"pnr":"' . $arFields['ConfNo'] . '","lastName":"' . $arFields['LastName'] . '"}}');

        $response = json_decode($this->http->Response['body']);
        $this->logger->debug(var_export($response, true), ["pre" => true]);

        if (isset($response->response->pnrInfo)) {
            $it = $this->ParseItinerary($response->response->pnrInfo);
        } elseif (isset($response->status->status) && $response->status->status == 'ERROR') {
            return 'Invalid Confirmation code number or Name. Please double check your entry.';
        }/*review*/

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Transaction" => "Info",
            "Description" => "Description",
            "Points"      => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->FilterHTML = false;
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $this->logger->debug("Found " . count($this->history) . " items");

        foreach ($this->history as $row) {
            if (!isset($row->points, $row->activityDateTime)) {
                $this->logger->error("Wrong row");

                continue;
            }
            // ISO 8601
            $dateStr = $row->activityDateTime;
            $this->logger->debug("Date: {$dateStr}");

            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                continue;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Transaction'] = beautifulName(preg_replace('/^POINTS_/ims', '', $row->pointsStatus));
            $result[$startIndex]['Description'] = $row->description ?? null;
            $result[$startIndex]['Points'] = $row->points;
            $startIndex++;
        }

        return $result;
    }

    public function findSimilarItinerary($haystack, $needle)
    {
        $this->logger->notice(__METHOD__);
        $gotIt = false;

        foreach ($haystack as $it) {
            if (isset($it['RecordLocator']) and isset($needle['RecordLocator'])
                and isset($it['TripSegments']) and isset($needle['TripSegments'])
                and $it['RecordLocator'] == $needle['RecordLocator']
                and count($it['TripSegments']) == count($needle['TripSegments'])
                and count($it['TripSegments']) > 0) {
                $segmentsAreSame = true;

                for ($i = 0; $i < count($it['TripSegments']); $i++) {
                    if (isset($it['TripSegments'][$i]['FlightNumber'])
                        and isset($needle['TripSegments'][$i]['FlightNumber'])
                        and $it['TripSegments'][$i]['FlightNumber'] == $needle['TripSegments'][$i]['FlightNumber']) {
                        continue;
                    } else {
                        $segmentsAreSame = false;

                        break;
                    }
                }

                if ($segmentsAreSame) {
                    $gotIt = true;

                    break;
                }
            }
        }

        return $gotIt;
    }

    private function parseRoundTripItinerary($itinerary)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->logger->debug(var_export($itinerary, true), ["pre" => true]);

        if (!isset($itinerary->pnr)) {
            $this->logger->error("PNR is not found");

            return $result;
        }
        $this->logger->info('Parse itinerary #' . $itinerary->pnr, ['Header' => 3]);
        $result['RecordLocator'] = $itinerary->pnr;

        foreach ($itinerary->segDetails as $seg) {
            // FlightNumber
            $segment['FlightNumber'] = $seg->flightNumber;
            // DepCode, DepName
            $segment['DepCode'] = $segment['DepName'] = $seg->origin;
            // DepDate
            $depD = explode('T', $seg->departureDateTime);
            $depDate = $depD[0];
            $depTime = preg_replace('/-.+/', '', $depD[1]);
            $this->http->Log("DepDate: $depDate $depTime");
            $depDate = strtotime($depDate . ' ' . $depTime);

            if ($depDate) {
                $segment['DepDate'] = $depDate;
            }
            // ArrCode, ArrName
            $segment['ArrCode'] = $segment['ArrName'] = $seg->dest;
            // ArrDate
            $arrD = explode('T', $seg->arrivalDateTime);
            $arrDate = $arrD[0];
            $arrTime = preg_replace('/-.+/', '', $arrD[1]);
            $this->http->Log("ArrDate: $arrDate $arrTime");
            $arrDate = strtotime($arrDate . ' ' . $arrTime);

            if ($arrDate) {
                $segment['ArrDate'] = $arrDate;
            }

            $result['TripSegments'][] = $segment;
        }

        return $result;
    }

    private function ParseItinerary($itinerary)
    {
        $result = [];
        $this->logger->debug(var_export($itinerary, true), ["pre" => true]);

        if (!isset($itinerary->pnr)) {
            $this->logger->error("PNR is not found");

            return $result;
        }
        $this->logger->info('Parse itinerary #' . $itinerary->pnr, ['Header' => 3]);
        // ConfirmationNumber
        $result['RecordLocator'] = $itinerary->pnr;
        // ReservationDate
        $resDate = explode('T', $itinerary->creationDate);
        $reservationDate = $resDate[0] . ' ' . preg_replace('/-.+/', '', $resDate[1]);
        $this->logger->debug("ReservationDate: $reservationDate");
        $result['ReservationDate'] = strtotime($reservationDate);
        // AccountNumber
        foreach ($itinerary->paxList as $passenger) {
            if (isset($passenger->loyaltyInfo->loyaltyId)) {
                $passAcc[] = $passenger->loyaltyInfo->loyaltyId;
            }

            if (isset($passenger->firstName, $passenger->lastName)) {
                $result['Passengers'][] = beautifulName($passenger->firstName . " " . $passenger->lastName);
            }

            if (isset($passenger->ticketNum)) {
                $result['TicketNumbers'][] = $passenger->ticketNum;
            }
        }// foreach ($itinerary->paxList as $passenger)

        if (isset($passAcc)) {
            $result['AccountNumbers'] = implode(', ', $passAcc);
        }
        // TotalCharge
        $result['TotalCharge'] = $itinerary->pnrFareInfo->pnrTotalCost ?? null;
        // BaseFare
        $result['BaseFare'] = $itinerary->pnrFareInfo->fareInformation->totalBaseFare ?? null;
        // Tax
        $result['Tax'] = $itinerary->pnrFareInfo->fareInformation->totalTax ?? null;
        // Currency
        $result['Currency'] = 'USD'; //hard code

        // Air Trip Segments
        foreach ($itinerary->oAndDList as $seg) {
            // new json format ?
            if (empty($seg->flightSeg->flightNum) && !empty($seg->connectingFlightList)) {
                foreach ($seg->connectingFlightList as $connectingSeg) {
                    // one more json format
                    if (empty($connectingSeg->flightSeg->flightNum) && !empty($connectingSeg->flightSegList)) {
                        $this->logger->notice("new json v.2");

                        foreach ($connectingSeg->flightSegList as $flightSeg) {
//                            $this->logger->debug(var_export($flightSeg, true), ["pre" => true]);
                            $flightSeg->flightSeg = $flightSeg;
                            $segment = $this->parseSegment($flightSeg, $itinerary);
                            $result['TripSegments'][] = $segment;
                        }// foreach ($connectingSeg->flightSegList as $flightSeg)
                    }// if (isset($connectingSeg->flightSegList))
                    else {
                        $this->logger->notice("new json v.1");
                        $segment = $this->parseSegment($connectingSeg, $itinerary);
                        $result['TripSegments'][] = $segment;
                    }
                }// foreach ($seg->connectingFlightList as $connectingSeg)
            }// if (empty($seg->flightSeg->flightNum) && !empty($seg->connectingFlightList))
            else {
                // standard json v.2
                if (empty($seg->flightSeg->flightNum) && !empty($seg->flightSegList)) {
                    $this->logger->notice("standard json v.2");

                    foreach ($seg->flightSegList as $flightSeg) {
//                        $this->logger->debug(var_export($flightSeg, true), ["pre" => true]);
                        $flightSeg->flightSeg = $flightSeg;
                        $segment = $this->parseSegment($flightSeg, $itinerary);
                        $result['TripSegments'][] = $segment;
                    }// foreach ($seg->flightSegList as $flightSeg)
                }// if (empty($seg->flightSeg->flightNum) && !empty($seg->flightSegList))
                else {
                    // standard json v.1
                    $this->logger->notice("standard json v.1");
                    $segment = $this->parseSegment($seg, $itinerary);
                    $result['TripSegments'][] = $segment;
                }
            }
        }// foreach ($itinerary->oAndDList as $list)

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }
}
