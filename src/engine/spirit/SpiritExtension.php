<?php

namespace AwardWallet\Engine\spirit;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
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
use function AwardWallet\ExtensionWorker\beautifulName;

class SpiritExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface, ParseHistoryInterface
{
    use TextTrait;
    private int $stepItinerary = 0;

    private $headers = [
        'Accept' => 'application/json, text/plain, */*',
        'Content-Type' => 'application/json',
        'Origin' => 'https://www.spirit.com',
        'Referer' => 'https://www.spirit.com/',
        'Ocp-Apim-Subscription-Key' => '3b6a6994753b4efc86376552e52b8432',
    ];


    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.spirit.com/account/dashboard";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        sleep(5); // load page
        // Hold press captcha
        $result = $tab->evaluate('//input[@id="username"] | //a[contains(@href,"/account/edit-profile")]', EvaluateOptions::new()->timeout(50));
        return $result->getNodeName() == 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//*[@id="free-spirit-number"]', FindTextOptions::new()->preg('/#(\d+)\b/')->visible(false));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//li[contains(@class,"menu-item")]/a[contains(text(),"Sign Out")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//a[contains(text(),"Sign-In")]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@id="username"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@type="submit" and contains(text(),"Log In")]')->click();

        $result = $tab->evaluate('//div[contains(@class,"alert alert-danger")]/p', EvaluateOptions::new()->timeout(7)->allowNull(true));
        if ($result && stripos($result->getInnerText(), "Invalid email address or incorrect password. Please correct") !== false) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        $result = $tab->evaluate('//a[contains(@href,"/account/edit-profile")]', EvaluateOptions::new()->visible(false));
        if ($result->getNodeName() == 'A') {
            $this->logger->info('logged in');
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }



    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {

        $st = $master->createStatement();
        $accountDetail = $this->getAccountDetail($tab);
        $firstName = $accountDetail->data->person->name->first ?? null;
        $lastName = $accountDetail->data->person->name->last ?? null;
        // Name
        $st->addProperty('Name', beautifulName("$firstName $lastName"));

        $mainProgram = $accountDetail->data->person->programs[0];
        if (count($accountDetail->data->person->programs) > 0) {
            $this->logger->debug('multiple programs were found');
            $countOfPrograms = 0;

            foreach ($accountDetail->data->person->programs as $program) {
                if ($program->programCode == 'FS') {
                    $this->logger->notice("skip program code 'FS'");

                    continue;
                }

                if ($program->programCode == 'NK') {
                    $mainProgram = $program;
                }
                $countOfPrograms++;
            }

            if ($countOfPrograms != 1) {
                return;
            }
        }
        $number = $mainProgram->programNumber ?? null;

        if (!$number) {
            $this->logger->error("programNumber not found");

            return;
        }
        // Balance - Your Current Miles
        $st->setBalance($mainProgram->pointBalance ?? null);
        // Free Spirit Account Number
        $st->setNumber($number);

        // Mileage Earning Tier
        $st->addProperty("Status", $accountDetail->data->tierStatus ?? null);
        // Status Expiration - Free Spirit Silver  Valid through
        $tierEndDate = strtotime($accountDetail->data->tierEndDate);

        if ($tierEndDate >= strtotime('now')) {
            $st->addProperty("StatusExpiration", date("F d, Y", $tierEndDate));
        }

        // Spirit $9 Fare Club    // refs #5997
        $this->logger->info('Savers$ Club Membership', ['Header' => 3]);
        // Date Joined
        $joined = $accountDetail->data->clubMembership->subscriptionStartDate ?? null;
        // Renewal Date
        $exp = $accountDetail->data->clubMembership->subscriptionEndDate ?? null;
        // Days left in membership
        $day = $accountDetail->data->clubMembership->daysLeftInMembership ?? null;

        if (strtotime($exp) && isset($day) && $day > 0) {// bug fix (AccountID: 4143643)
            $st->addProperty("CombineSubAccounts", false);
            $st->addSubAccount([
                'Code'                 => 'spiritSaversClubMembership',
                'DisplayName'          => 'Savers$ Club Membership',
                'Balance'              => null,
                'DateJoined'           => date("F d, Y", strtotime($joined)),
                // Days left in membership
                // refs#20162 'DaysLeftInMembership' => $day,
                'ExpirationDate'       => strtotime($exp),
            ]);
        }
        // Expiration Date   // refs #5780
        if ($st->getBalance() > 0) {
            $transactions = $this->getHistory($tab, $number)->data->mileageStatementInfo->customerPointsBreakdown ?? [];
            $this->logger->debug("Total " . count($transactions) . " transactions were found");
            foreach ($transactions as $transaction) {
                $dateStr = $transaction->dateEarned;
                $credit = $transaction->credit;
                $postDate = strtotime($dateStr);

                if ((!empty($credit) || !empty($transaction->debit)) && $postDate) {
                    // Last Activity
                    $st->addProperty("LastActivity", date("m/d/Y", $postDate));
                    $st->setExpirationDate(strtotime("+12 month", $postDate));
                    break;
                }
            }
        }

        $st->addProperty('StatusQualifyingPoints', $accountDetail->data->clubMembership->lifetimeAccumulatedQualifyingPoints ?? 0);

        /*$memberTQPInfo = $this->getMemberTQPInfo($tab, $st->getNumber());
        if (isset($memberTQPInfo->data->memberTQPInfo->totalTQP)) {
            $st->addProperty('StatusQualifyingPoints', $memberTQPInfo->data->memberTQPInfo->totalTQP);
        }*/
    }
    private function getAccountDetail(Tab $tab)
    {
        $this->logger->notice(__METHOD__);
        $token = $tab->getFromLocalStorage('token');
        $options = [
            'method' => 'get',
            'mode' => 'cors',
            'credentials' => 'same-origin',
            'headers' => $this->headers + [
                'Authorization' => $token
            ],
        ];
        try {
            $json = $tab->fetch('https://api.spirit.com/prod-account/api/Account/accountdetail', $options)->body;
            $this->logger->info($json);

            return json_decode($json);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return null;
    }

    private function getMemberTQPInfo(Tab $tab, string $number)
    {
        $this->logger->notice(__METHOD__);
        $token = $tab->getFromLocalStorage('token');
        $options = [
            'method'  => 'post',
            'mode' => 'cors',
            'credentials' => 'same-origin',
            'headers' => $this->headers + [
                'Authorization' => $token
            ],
            'body' => '{"operationName":null,"variables":{},"query":"{\n  memberTQPInfo(freeSpiritNumber: \"'.$number.'\") {\n    creditCardTQP\n    extrasTQP\n    fareTQP\n    totalTQP\n    spiritTQPYTDBalance\n    spiritTQPMonthBalance\n    overrideTQP\n    __typename\n  }\n}\n"}'
        ];
        try {
            $json = $tab->fetch('https://api.spirit.com/prod-account/graphql', $options)->body;
            $this->logger->info($json);

            return json_decode($json);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return null;
    }

    public function getHistory(Tab $tab, string $number)
    {
        $this->logger->notice(__METHOD__);
        $token = $tab->getFromLocalStorage('token');
        $transactionPeriodStartDate = date("Y-n-j", strtotime("-5 years"));
        $transactionPeriodEndDate = date("Y-n-j");
        $options = [
            'method' => 'post',
            'mode' => 'cors',
            'credentials' => 'same-origin',
            'headers' => $this->headers  + [
                'Authorization' => $token
            ],
            'body' => '{"operationName":null,"variables":{},"query":"{\n  mileageStatementInfo(\n    statementRequest: {accountNumber: \"'.$number.'\", transactionPeriodStartDate: \"'.$transactionPeriodStartDate.'\", transactionPeriodEndDate: \"'.$transactionPeriodEndDate.'\", transactionType: \"ALL\", lastID: 0, pageSize: 1000}\n  ) {\n    customerPointsBreakdown {\n      balance\n      category\n      credit\n      dateEarned\n      debit\n      description\n      ccQualifyingPoints\n      nkCcQualifyingPoints\n      nkQualifyingPoints\n      referenceNumber\n      __typename\n    }\n    startDate\n    startingBalance\n    startingBalanceSpecified\n    __typename\n  }\n}\n"}'
        ];
        try {
            $json = $tab->fetch('https://api.spirit.com/prod-account/graphql', $options)->body;
            $this->logger->info($json);

            return json_decode($json);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return null;
    }

    public function getItineraries(Tab $tab)
    {
        $this->logger->notice(__METHOD__);
        $token = $tab->getFromLocalStorage('token');
        $options = [
            'method' => 'post',
            'mode' => 'cors',
            'credentials' => 'same-origin',
            'headers' => $this->headers + [
                'Authorization' => $token
            ],
            'body' => '{"operationName":null,"variables":{},"query":"{\n  findUserBookings(\n    searchRequest: {includeDistance: true, returnCount: 100, includeAccrualEstimate: true, searchByCustomerNumber: true}\n  ) {\n    currentBookings {\n      allowedToModifyGdsBooking\n      bookingKey\n      bookingStatus\n      channelType\n      destination\n      distance\n      editable\n      expiredDate\n      flightDate\n      flightNumber\n      name {\n        first\n        last\n        __typename\n      }\n      origin\n      passengerId\n      recordLocator\n      sourceAgentCode\n      sourceDomainCode\n      sourceOrganizationCode\n      systemCode\n      qualifyingPoints\n      redeemablePoints\n      __typename\n    }\n    __typename\n  }\n}\n"}'
        ];
        try {
            $json = $tab->fetch('https://api.spirit.com/prod-user/graphql', $options)->body;
            $this->logger->info($json);
            return $json;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return null;
    }

    public function parseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        $itinerariesText = $this->getItineraries($tab);
        $itineraries = json_decode($itinerariesText);
        $itineraries = $itineraries->data->findUserBookings->currentBookings ?? [];
        $this->logger->debug("Total " . count($itineraries) . " itineraries were found");

        if (count($itineraries) == 0 && $this->findPreg('/"currentBookings":\[\],/', $itinerariesText)) {
            $master->setNoItineraries(true);
            return;
        }
        $token = $tab->getFromLocalStorage('token');
        foreach ($itineraries as $item) {
            $options = [
                'method' => 'post',
                'headers' => $this->headers + [
                    'Authorization' => "Bearer $token",
                    'X-Ignore-Toast' => true
                ],
                'body' => json_encode([
                    'lastName'      => $item->name->last,
                    'recordLocator' => $item->recordLocator,
                ])
            ];
            try {
                $json = $tab->fetch('https://www.spirit.com/api/prod-booking/api/booking/retrieve', $options)->body;
                $this->logger->info($json);
                $this->parseItinerary($tab, $master, json_decode($json));
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    private function parseItinerary(Tab $tab, Master $master, $data)
    {
        $this->logger->notice(__METHOD__);

        $conf = $data->data->recordLocator;
        $this->logger->info(sprintf("[%s] Parse Itinerary #%s", $this->stepItinerary++, $conf), ['Header' => 3]);
        $f = $master->createFlight();
        $f->general()->confirmation($conf);
        $f->general()
            ->date2($data->data->info->bookedDate);

        switch ($data->data->info->status) {
            case '2':
                $status = 'Confirmed';
                $f->general()->status($status);

                break;

            case '3':
            case '4':
                $status = 'Cancelled';
                $f->general()->status($status);
                if ($data->isCancelled ?? null == true) {
                    $f->general()->cancelled();
                }
                break;

            default:
                $this->logger->notice("Unknown status {$data->data->info->status} // RR");
        }

        //$f->price()->currency($data->currencyCode ?? null);
        $f->price()->total($data->data->breakdown->totalAmount);

        $breakdown = $data->data->priceDisplay->flightPrice->breakdown ?? [];

        foreach ($breakdown as $price) {
            if ($price->display == 'Flight Price' && $price->price > 0) {
                //$f->price()->cost($price->price);

                continue;
            }
            $f->price()->fee($price->display, round($price->price, 2));
        }

        if ($data->data->priceDisplay->bags->total > 0) {
            $f->price()->fee("Bags", $data->data->priceDisplay->bags->total);
        }

        if ($data->data->priceDisplay->seats->total > 0) {
            $f->price()->fee("Seats", $data->data->priceDisplay->seats->total);
        }

        $passengers = $data->data->passengers ?? [];

        foreach ($passengers as $key => $passenger) {
            $frequentFlyer = $passenger->accountNumber ?? null;

            if ($frequentFlyer) {
                $f->program()->account($frequentFlyer, false);
            }
            $f->general()->traveller(beautifulName($passenger->name->first . " " . ($passenger->name->middle ?? '') . " " . $passenger->name->last), true);
        }

        // Air Trip Segments
        $journeys = $data->data->journeys ?? [];
        $this->logger->debug("Total " . count($journeys) . " journeys were found");

        foreach ($journeys as $journey) {
            $segments = $journey->segments ?? [];
            $this->logger->debug("Total " . count($segments) . " segments were found");

            foreach ($segments as $segment) {
                $legs = $segment->legs ?? [];
                $this->logger->debug("Total " . count($legs) . " legs were found");

                foreach ($legs as $leg) {
                    $s = $f->addSegment();

                    $s->airline()
                        ->name($segment->identifier->carrierCode)
                        ->number($segment->identifier->identifier);

                    $s->extra()
                        ->cabin($segment->cabinOfService)
                        ->aircraft($leg->legInfo->equipmentType, true, true)
                        ->duration(preg_replace("/:\d+$/", "", $leg->travelTime))
                        ->stops($journey->stops);

                    if (!empty($leg->distanceInMiles)) {
                        $s->extra()
                            ->miles($leg->distanceInMiles);
                    }

                    $s->departure()
                        ->code($leg->designator->origin)
                        ->terminal($leg->legInfo->departureTerminal ?? null, true, true)
                        ->date2($leg->designator->departure);

                    $s->arrival()
                        ->code($leg->designator->destination)
                        ->terminal($leg->legInfo->arrivalTerminal ?? null, true, true)
                        ->date2($leg->designator->arrival);
                }
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    public function parseHistory(
        Tab                 $tab,
        Master              $master,
        AccountOptions      $accountOptions,
        ParseHistoryOptions $historyOptions
    ): void {
        $startDate = $historyOptions->getStartDate();
        $this->logger->debug('[History start date: ' . ($startDate ? $startDate->format('Y/m/d H:i:s') : 'all') . ']');

        if (isset($startDate)) {
            $startDate = $startDate->format('U');
        } else {
            $startDate = 0;
        }
        $statement = $master->getStatement();
        $transactions = $this->getHistory($tab, $statement->getNumber())->data->mileageStatementInfo->customerPointsBreakdown ?? [];
        $this->parsePageHistory($statement, $transactions, $startDate);
    }

    public function parsePageHistory(Statement $statement, $transactions, $startDate)
    {
        $this->logger->notice(__METHOD__);
        foreach ($transactions as $transaction) {
            $postDate = strtotime($transaction->dateEarned);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$transaction->dateEarned} ($postDate)");

                continue;
            }
            $result = [];
            $result['Date'] = $postDate;
            $result['Transaction'] = $transaction->description;
            $debit = intval(preg_replace('/,/', '', $transaction->debit));

            if ($debit < 0) {
                $debit *= -1;
            }
            $credit = intval(preg_replace('/,/', '', $transaction->credit));
            $result['Points'] = $credit - $debit;
            $result['Balance'] = $transaction->balance;
            $statement->addActivityRow($result);
        }
    }
}
