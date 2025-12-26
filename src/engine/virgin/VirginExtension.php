<?php

namespace AwardWallet\Engine\virgin;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ActiveTabInterface;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\ElementNotFoundException;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseHistoryInterface;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Common\Statement;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;
use Exception;
use function AwardWallet\ExtensionWorker\beautifulName;

class VirginExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface, ParseHistoryInterface, ActiveTabInterface
{
    use TextTrait;

    private int $stepItinerary = 0;

    public function isActiveTab(AccountOptions $options): bool
    {
        return true;
    }
    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.virginatlantic.com/myflyingclub/dashboard";
    }

    private function buttonSwitch(Tab $tab)
    {
        $button = $tab->evaluate('//button[@aria-label="Flying Club"] | //button[contains(text(),"United States - English")] | //input[@id="userId"] | //button[contains(@class, "login-btn")]',
            EvaluateOptions::new()->allowNull(true));

        if ($button) {
            $this->logger->debug($button->getNodeName() . ' ' . $button->getInnerText());
            // popup
            if (
                str_contains($button->getInnerText(), 'United States - English')
                || str_contains($button->getAttribute('class'), "login-btn")
            ) {
                $button->click();
            } // home page
            elseif (
                str_contains($button->getAttribute('aria-label'), 'Flying Club')
            ) {
                $tab->gotoUrl('https://www.virginatlantic.com/myflyingclub/dashboard');
                $button = $tab->evaluate('//button[contains(text(),"United States - English")]',
                    EvaluateOptions::new()->timeout(7)->allowNull(true));
                if ($button && str_contains($button->getInnerText(), 'United States - English')) {
                    $button->click();
                }
            }
        } else {
            $this->logger->debug('no button');
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->buttonSwitch($tab);

        $result = $tab->evaluate('//input[@id="userId"] | //td[@class="fcPanelTop1SubDivBody MemberShipNo"]');
        return $result->getNodeName() == 'TD';
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.virginatlantic.com/myflyingclub/dashboard');
        sleep(1);
        return $tab->findText('//td[@class="fcPanelTop1SubDivBody MemberShipNo"]',
            FindTextOptions::new()->preg('/^(\d+)$/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//div[contains(@class,"logged-in-container logged-in-flyout")]')->click();
        $tab->evaluate('//div[contains(@class,"loggedin-flyout")]//a[contains(text(),"Log out")]')->click();
        sleep(1);
        $tab->evaluate('//input[@id="userId"] | //button[@aria-label="Flying Club"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $loginInput = $tab->evaluate('//input[@id="userId"]');
        $loginInput->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="password"]')->setValue($credentials->getPassword());
        $loginInput->focus();
        $lastNameInput = $tab->evaluate('//input[@id="lastName"]', EvaluateOptions::new()->allowNull(true)->timeout(3));
        if ($lastNameInput) {
            $lastNameInput->focus();
            $lastNameInput->setValue($credentials->getLogin2());
        }
        $tab->evaluate('//div[@class="loginButtonDiv"]/button')->click();

        $result = $tab->evaluate('//div[@id="overlayDiv"]/div[contains(@class,"overlayText")] 
        | //button[@aria-label="Flying Club"]
        | //div[contains(@class, "logged-in-container logged-in-flyout")] | //span[@id="userId-error"] | //span[@id="password-error"]',
            EvaluateOptions::new()->timeout(30));
        if (str_starts_with($result->getInnerText(), "Hmm... The details you’ve entered aren't quite right.")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        // Sorry, something went wrong. Please try again later ERROR_MSG_21
        if (str_starts_with($result->getInnerText(), "Sorry, something went wrong. Please try again later ERROR_MSG_21")) {
            return LoginResult::providerError($result->getInnerText());
        }

        if (str_starts_with($result->getInnerText(), "To continue")) {
            return LoginResult::providerError($result->getInnerText());
        }

        if($result->getNodeName() == 'SPAN' && strstr($result->getAttribute('id'), "error")) {
            return new LoginResult(false, $result->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        $tab->gotoUrl('https://www.virginatlantic.com/myflyingclub/dashboard');
        $result = $tab->evaluate('//td[@class="fcPanelTop1SubDivBody MemberShipNo"]');
        if ($result->getNodeName() == 'TD' || str_contains($result->getAttribute('class'), 'logged-in-container')) {
            $this->logger->info('logged in');
            return new LoginResult(true);
        } else {
            return new LoginResult(false);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        sleep(3);

        $st = $master->createStatement();

        // Your Virgin Points balance
        $st->setBalance(str_replace(',', '',
            $tab->findText('//h2[contains(text(),"Your") and contains(.,"balance")]/following-sibling::div[1]',
                FindTextOptions::new()->preg('/^([\d.,]+)/'))));

        // Membership no
        $st->addProperty('Number', $tab->findText('//td[@class="fcPanelTop1SubDivBody MemberShipNo"]',
            FindTextOptions::new()->preg('/^\w+$/')));

        $loginData = $tab->findText('//script[contains(text(),"loginData")]', FindTextOptions::new()->visible(false));
        $firstName = $this->findPreg('/"firstName":"(.+?)"/', $loginData);
        $lastName = $this->findPreg('/"lastName":"(.+?)"/', $loginData);
        // Name
        $st->addProperty('Name', beautifulName("$firstName $lastName"));
        // Member since
        $st->addProperty('MemberSince', strtotime($tab->findText('//td[@class="fcPanelTop1SubDivBody memberSince"]')));
        // Tier points
        $st->addProperty('TierPoints',
            $tab->findText('//h2[contains(text(),"Tier points")]/following-sibling::div[1]'));
        // Red member
        $st->addProperty('EliteStatus',
            $tab->findText('//h2[contains(text(),"Tier points")]/following-sibling::h3[1]'));
        // Expiration date  refs 15041#note-24
        $exp = $tab->findTextNullable('//th[contains(text(),"Miles expiry date")]/following-sibling::td[not(contains(@class,"noDisplay"))]');
        $this->logger->debug("Exp date: {$exp}");
        if ($exp = strtotime($exp)) {
            $st->setExpirationDate($exp);
        }
    }

    public function parseItineraries(
        Tab                     $tab,
        Master                  $master,
        AccountOptions          $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void
    {
        $tab->evaluate('//li[contains(text(),"My Flights") and contains(@class,"inactiveTab")]')->click();
        $tab->evaluate('(//form[contains(@id,"view_details_")])[1]', EvaluateOptions::new()->allowNull(true)->timeout(4));
        $confirmationNumbers = $tab->evaluateAll('//form[contains(@id,"view_details_")]/input[@name="confirmationNo"]');
        $this->logger->debug("Total " . count($confirmationNumbers) . " itineraries were found");

        if (count($confirmationNumbers) == 0 && $tab->findTextNullable('//h2[contains(text(),"Are you missing a flight ?")]')) {
            $master->setNoItineraries(true);
            return;
        }

        $this->openItinerary($tab, $master);
    }

    private function openItinerary(Tab $tab, Master $master)
    {
        $this->logger->notice(__METHOD__);
        try {
            $form = $tab->evaluate("//form[@id='view_details_{$this->stepItinerary}']",
                EvaluateOptions::new()->timeout(10));
            /*$options = [
                'method' => 'post',
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'firstName' => $tab->findText('./input[@name="firstName"]/@value', FindTextOptions::new()->contextNode($form)),
                    'lastName' => $tab->findText('./input[@name="lastName"]/@value', FindTextOptions::new()->contextNode($form)),
                    'confirmationNo' => $tab->findText('./input[@name="confirmationNo"]/@value', FindTextOptions::new()->contextNode($form)),
                    'tab' => $tab->findText('./input[@name="tab"]/@value', FindTextOptions::new()->contextNode($form)),
                    'flagFromUpcomingTrips' => $tab->findText('./input[@name="flagFromUpcomingTrips"]/@value', FindTextOptions::new()->contextNode($form)),
                    'returnAction' => $tab->findText('./input[@name="returnAction"]/@value', FindTextOptions::new()->contextNode($form)),
                ])
            ];
            $response = $tab->fetch('https://www.virginatlantic.com/mytrips/findPnr', $options);
            $this->logger->debug("Redirect: $response->url");
            $this->logger->debug("body: $response->body");
            $this->logger->debug("Headers: " . var_export($response->headers, true));
            $this->logger->debug("Status: $response->status");*/
            $options = [
                'method' => 'post',
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Origin' => 'https://www.virginatlantic.com',
                ],
                'body' => http_build_query([
                    'firstName' => $tab->findText('./input[@name="firstName"]/@value', FindTextOptions::new()->contextNode($form)),
                    'lastName' => $tab->findText('./input[@name="lastName"]/@value', FindTextOptions::new()->contextNode($form)),
                    'confirmationNo' => $tab->findText('./input[@name="confirmationNo"]/@value', FindTextOptions::new()->contextNode($form)),
                    'tab' => $tab->findText('./input[@name="tab"]/@value', FindTextOptions::new()->contextNode($form)),
                    'flagFromUpcomingTrips' => $tab->findText('./input[@name="flagFromUpcomingTrips"]/@value', FindTextOptions::new()->contextNode($form)),
                    'returnAction' => $tab->findText('./input[@name="returnAction"]/@value', FindTextOptions::new()->contextNode($form)),
                    'interstitial' => 'true'
                ])
            ];
            $response = $tab->fetch('https://www.virginatlantic.com/mytrips/findPnr.action', $options);
            $this->logger->debug("Redirect: $response->url");
            $this->logger->debug("body: $response->body");
            $this->logger->debug("Headers: " . var_export($response->headers, true));
            $this->logger->debug("Status: $response->status");

            $recordLocator = $this->findPreg('/recordLocator=(.+?)&/', $response->url);
            if (!isset($recordLocator)) {
                $this->logger->error("No recordLocator found");
                return;
            }

            $this->logger->debug("Body: " . var_export(json_encode([
                    'using' => 'CONFIRMATION',
                    'encryptedConfirmationNum' => $recordLocator,
                    'givenNames' => $this->findPreg('/firstName=(.+?)&/', $response->url),
                    'surname' => $this->findPreg('/lastName=(.+?)&/', $response->url),
                ], JSON_UNESCAPED_SLASHES), true));

            $options = [
                'method' => 'post',
                'headers' => [
                    'Accept' => 'application/json, text/plain, */*',
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'appid'=> 'WEB',
                    'channelid' => 'MyTrip',
                    'transactionid' => 'my-trips-01938d22-e2a2-4449-9dd5-582ce9351c36',
                    'Origin' => 'https://www.virginatlantic.com'
                ],
                'body' => json_encode([
                    'using' => 'CONFIRMATION',
                    'encryptedConfirmationNum' => $recordLocator,
                    'givenNames' => $this->findPreg('/firstName=(.+?)&/', $response->url),
                    'surname' => $this->findPreg('/lastName=(.+?)&/', $response->url),
                ], JSON_UNESCAPED_SLASHES)
            ];
            $data = $tab->fetch('https://mytrips-api.vs.air4.com/v1/mytrips/travelreservations', $options)->body;
            $this->logger->debug($data);
            $data = json_decode($data);
            $this->parseItineraryV2($tab, $master, $data);
            $this->stepItinerary++;

            /*$this->logger->debug("open itinerary #{$this->stepItinerary}");
            $form->click();
            $this->parseItinerary($tab, $master);
            $tab->gotoUrl('https://www.virginatlantic.com/myflyingclub/dashboard');
            $tab->evaluate('//h2[contains(text(),"Your") and contains(text(),"balance")]/following-sibling::div[1]');
            $tab->evaluate('//li[contains(text(),"My Flights") and contains(@class,"inactiveTab")]')->click();
            $tab->evaluate('(//form[contains(@id,"view_details_")])[1]');
            $this->openItinerary($tab, $master);*/
        } catch (Exception $e) {
            $this->logger->notice("Stop parse itineraies: {$e->getMessage()}");
            $this->logger->debug($e->getMessage());
        }
    }

    private function parseItineraryV2(Tab $tab, Master $master, $data)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info(sprintf("[%s] Parse Itinerary #%s", $this->stepItinerary++, $data->travelReservationRequest->confirmationNum), ['Header' => 3]);
        $f = $master->createFlight();
        $f->general()->confirmation($data->travelReservationRequest->confirmationNum);

        foreach ($data->travelReservations as $travelReservation) {
            foreach ($travelReservation->passengers as $passenger) {
                $f->general()->traveller("$passenger->givenNames $passenger->surname");
                foreach ($passenger->tickets as $ticket) {
                    $f->issued()->ticket($ticket->number, false);
                }
                foreach ($passenger->loyaltyProgramAccounts as $loyaltyProgramAccount) {
                    $f->program()->account($loyaltyProgramAccount->number, false);
                }
            }

            foreach ($travelReservation->trips as $trip) {
                foreach ($trip->segments as $segment) {
                    foreach ($segment->legs as $leg) {
                        $s = $f->addSegment();
                        /*
                        $s->airline()->name($leg->operationalSuffix);
                        */
                        $s->airline()->name($segment->marketingSegment->operationalSuffix);

                        $s->airline()->number($leg->flightNum);
                        $s->departure()->code($leg->transportOrigin->station->code);
                        $s->arrival()->code($leg->transportDestination->station->code);
                        $s->departure()->date2($leg->transportOrigin->scheduledDepartureLocalDateTime);
                        $s->arrival()->date2($leg->transportDestination->scheduledArrivalLocalDateTime);

                        $s->extra()->aircraft($leg->transportEquipment->name);
                        $s->extra()->cabin($leg->cabinClass->name);

                        $s->extra()->duration($this->convertDuration($leg->onAirDuration));

                        foreach ($travelReservation->passengers as $passenger) {
                            foreach ($passenger->passengerTrips as $passengerTrip) {
                                if ($trip->tripId == $passengerTrip->tripId) {
                                    foreach ($passengerTrip->passengerSegments as $passengerSegment) {
                                        if ($segment->segmentId == $passengerSegment->segmentId) {
                                            $s->extra()->bookingCode($passengerSegment->bookedCabinClass->code);
                                            foreach ($passengerSegment->passengerLegs as $passengerLeg) {
                                                if ($leg->legId == $passengerLeg->legId) {
                                                    foreach ($passengerLeg->seatAssignments as $assignment) {
                                                        $s->extra()->seat($assignment->seat->number);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

        }

    }
    private function convertDuration($duration)
    {
        // PT2H10M
        if (preg_match("/^[A-Z]+(\d+)\s*H(\d+)M$/", $duration, $m)) {
            return sprintf('%01dh %02dm', $m[1], $m[2]);
        }
        // todo
        elseif (preg_match("/^[A-Z]+(\d+)M$/", $duration, $m)) {
            return sprintf('%01dh %02dm', 0, $m[1]);
        }
        // PT8H
        elseif (preg_match("/^[A-Z]+(\d+)\s*H$/", $duration, $m)) {
            return sprintf('%01dh', 0, $m[1]);
        }

        return null;
    }
    private function parseItinerary(Tab $tab, Master $master)
    {
        $this->logger->notice(__METHOD__);

        $conf = $tab->findText('//span[contains(@class,"bcReferenceNum")]');

        $this->logger->info(sprintf("[%s] Parse Itinerary #%s", $this->stepItinerary++, $conf), ['Header' => 3]);
        $f = $master->createFlight();
        $f->general()->confirmation($conf);

        $f->general()->travellers(array_map('beautifulName',
            $tab->findTextAll("//div[contains(@class, 'PassDetailsBlock')]/div[contains(@class,'passengersNameUpper')]",
                FindTextOptions::new()->preg("/^(.+?)\s*(?:\(|$)/"))));
        $f->issued()->tickets($tab->findTextAll("//div[contains(@class, 'PassDetailsBlock')]//div[contains(text(), 'E-ticket')or contains(text(), 'eTicket')]/span[contains(@class, 'number')]"),
            false);
        $f->program()->accounts($tab->findTextAll("//span[@id = 'frequentFlyerNumberTrip']"), false);


        $segments = $tab->evaluateAll("//div[contains(@id, 'itin_')]");
        $this->logger->debug("Total " . count($segments) . " segments were found");
        $invalidSegment = false;
        $curveCodes = ['OPE', 'OPB', 'OPE', 'NTK', 'REM', 'VVD', 'VOU', 'UCH'];
        foreach ($segments as $seg) {
            // FlightNumber and AirlineName
            $flightNumber = $tab->findText(".//p[contains(@class, 'flightNumber')]/text()[last()]",
                FindTextOptions::new()->contextNode($seg));
            if ($this->findPreg('/^[A-Z]{2}$/', $flightNumber)) {
                $depTime = $tab->findText('.//span[contains(@class, "departTimeFormat")]',
                    FindTextOptions::new()->contextNode($seg));
                $arrTime = $tab->findText('.//span[contains(@class, "arraivalTimeFormat")]',
                    FindTextOptions::new()->contextNode($seg));

                if ($depTime === '12:00 AM' && $arrTime === '12:00 AM') {
                    $this->logger->error('Skipping invalid segment');
                    $invalidSegment = true;
                } else {
                    $this->logger->error('check invalid segment // MI');
                }

                continue;
            }
            $s = $f->addSegment();
            $s->airline()->name($this->findPreg('/(\w{2})\s*\d+/', $flightNumber));
            $s->airline()->number($this->findPreg('/\w{2}\s*(\d+)/', $flightNumber));
            $depart = $tab->findText(".//p[contains(@class, 'departReturnMainText')]",
                FindTextOptions::new()->contextNode($seg));

            // Atlanta (ATL), US to Munich (MUC), DE
            $s->departure()->name($this->findPreg('/^(.+?)\s+\(/', $depart));
            $s->departure()->code($this->findPreg('/^.+?\s+\(([A-Z]{3})\)/', $depart));
            $s->arrival()->name($this->findPreg('/\s+to\s+(.+?)\s+\(/', $depart));
            $s->arrival()->code($this->findPreg('/\s+to\s+.+?\s+\(([A-Z]{3})\)/', $depart));

            $node = $tab->evaluate('preceding-sibling::input[@class = "itineraryFlags" and contains(@ftnum,"' . $s->getFlightNumber() . '") 
                and @origcode="' . $s->getDepCode() . '" and @destcode="' . $s->getArrCode() . '"]',
                EvaluateOptions::new()->visible(false)->contextNode($seg));
            $segmentId = $node->getAttribute('segmentid');
            $legId = $node->getAttribute('legid');
            $this->logger->debug("segmentid: {$segmentId} / legid: {$legId}");

            if (!$legId || !$segmentId) {
                $this->logger->error('check invalid segment // MI');

                continue;
            }
            $s->extra()->seats($tab->findTextAll("//form[input[@name = 'legId' and @value = '{$legId}'] and input[@name = 'segmentNumber' and @value = '{$segmentId}']]/preceding-sibling::span[contains(@class, 'seatValignT')]/span[not(contains(., 'class'))]"));


            // DepDate
            $depTime = $node->getAttribute('scheddeptime');
            $depDate = $node->getAttribute('depdate') . " " . $depTime;
            $this->logger->debug("DepDate: {$depDate}");

            if (!empty(trim($depDate)) && strtotime($depDate)) {
                $s->departure()->date(strtotime($depDate));
            }
            // ArrDate
            $arrTime = $node->getAttribute('schedarrtime');
            $arrDate = $node->getAttribute('arrdate') . " " . $arrTime;
            $this->logger->debug("ArrDate: {$arrDate}");

            if (!empty(trim($arrDate)) && strtotime($arrDate)) {
                $s->arrival()->date(strtotime($arrDate));
            }
            $duration = $node->getAttribute('flighttime');
            if (!empty($duration)) {
                $s->extra()->duration(sprintf("%0shr %0sm", (int)$duration, number_format(fmod($duration, 1) * 100)));
            }
            $s->extra()->miles($tab->findText(".//p[contains(@class, 'flightmiles')]/span",
                FindTextOptions::new()->contextNode($seg)));
            $s->extra()->aircraft($tab->findTextNullable(".//span[contains(@class,'aircraftName')]",
                FindTextOptions::new()->contextNode($seg)));
            $s->extra()->cabin($tab->findText(".//div[contains(@class,'flightStatusClass')]/span[
                not(contains(., 'Air'))
                and not(contains(., 'All '))
                and not(contains(., 'Operated by '))
            ] | .//span[contains(@class, 'fsrSmallFlightText')]/span[contains(., 'Cabin Class')]/following-sibling::span",
                FindTextOptions::new()->contextNode($seg)));

            $s->airline()->operator($tab->findText(".//div[contains(@class, 'fsrSmallFlightText')][starts-with(normalize-space(),'Operated by')]",
                FindTextOptions::new()->contextNode($seg)->preg("/Operated by\s*(.+)?(?:\s+DBA\s+|$)/")));

            if (in_array($s->getDepCode(), $curveCodes) && in_array($s->getArrCode(), $curveCodes)
                && ($s->getDepDate() == $s->getArrDate())
                && $s->getCabin() == "YY YY"
                && $s->getAirlineName() == 'YY' && $s->getFlightNumber() == '101'
            ) {
                //$segment = ['Cancelled' => true];
                $this->logger->error('Flight on hold or something');
                $invalidSegment = true;
                $f->removeSegment($s);


            }
        }
        if (count($f->getSegments()) === 0 && $invalidSegment) {
            $this->logger->error('Skipping invalid flight');
            $master->removeItinerary($f);
            return;
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    public function parseHistory(
        Tab                 $tab,
        Master              $master,
        AccountOptions      $accountOptions,
        ParseHistoryOptions $historyOptions
    ): void
    {
        $startDate = $historyOptions->getStartDate();
        $this->logger->debug('[History start date: ' . ($startDate ? $startDate->format('Y/m/d H:i:s') : 'all') . ']');

        if (isset($startDate)) {
            $startDate = $startDate->format('U');
        } else {
            $startDate = 0;
        }
        try {
            $tab->evaluate('//li[contains(text(),"Activity") and contains(@class,"inactiveTab")]')->click();
        } catch (ElementNotFoundException $e) {
            $this->logger->error($e->getMessage());
        }
        $tab->evaluate('//table[contains(@class,"activityTable")]//tr[td[contains(@headers,"tierPointCol") or @class="dataTables_empty"]]');
        $statement = $master->getStatement();
        $this->parsePageHistory($tab, $statement, $startDate);
    }

    public function parsePageHistory(Tab $tab, Statement $statement, $startDate)
    {
        $nodes = $tab->evaluateAll('//table[contains(@class,"activityTable")]//tr[td[contains(@headers,"tierPointCol")]]');
        $this->logger->debug('Total ' . count($nodes) . ' items were found');
        if (count($nodes) > 0) {
            foreach ($nodes as $node) {
                $row = [];
                $dateStr = $tab->findText("td[1]/text()[last()]", FindTextOptions::new()->contextNode($node));
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date $dateStr ($postDate)");

                    break;
                }
                $row['Date'] = $postDate;
                $row['Transaction Date'] = strtotime($tab->findText("td[2]/text()[last()]",
                    FindTextOptions::new()->contextNode($node)));
                $row['Activity'] = $tab->findText("td[3]/text()[last()]", FindTextOptions::new()->contextNode($node));

                if ($this->findPreg('/Bonus/ims', $row['Activity'])) {
                    $row['Bonus Mileage'] = trim($tab->findText("td[4]/text()[last()]",
                        FindTextOptions::new()->contextNode($node)));
                } else {
                    $row['Mileage'] = trim($tab->findText("td[4]/text()[last()]",
                        FindTextOptions::new()->contextNode($node)));
                }
                $row['Tier points'] = trim($tab->findText("td[5]/text()[last()]",
                    FindTextOptions::new()->contextNode($node)));
                $statement->addActivityRow($row);
            }
        } elseif ($message = $this->findPreg("/(No data available in table)/ims", $tab->getHtml())) {
            $this->logger->debug(">>> " . $message);
        }
    }
}
