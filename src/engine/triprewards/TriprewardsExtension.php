<?php

namespace AwardWallet\Engine\triprewards;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
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

class TriprewardsExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface, ParseHistoryInterface
{
    use TextTrait;

    private array $headers = [
        'Accept' => '*/*',
        'Content-Type' => 'application/json'
    ];
    private $currentItin = 0;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.wyndhamhotels.com/wyndham-rewards/my-account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $loginButton = $tab->evaluate("//div[.//div[contains(text(), 'SIGN IN')] and @data-dropdown='account'] | //h1[contains(@class,'hero-titles')]",
            EvaluateOptions::new()->nonEmptyString()->visible(false));

        return $loginButton->getNodeName() === 'H1';
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl("https://www.wyndhamhotels.com/wyndham-rewards/my-account");

        $loginId = $tab->evaluate("//p[contains(normalize-space(), 'member #')][1]/descendant::span[2]",
            EvaluateOptions::new()->nonEmptyString())->getInnerText();
        $this->logger->info('!' . $loginId);

        return $loginId;
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate("//text()[starts-with(normalize-space(), 'Hi')]/ancestor::div[1]",
            EvaluateOptions::new()->timeout(1))->click();

        $tab->evaluate("//text()[starts-with(normalize-space(), 'Hi')]/following::button[contains(@class, 'sign-out-button')][1]",
            EvaluateOptions::new()->timeout(2))->click();
        $tab->evaluate("//a[normalize-space()='JOIN NOW']");
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);

        $tab->evaluate("//div[.//div[contains(text(), 'SIGN IN')] and @data-dropdown='account']",
            EvaluateOptions::new()->timeout(30)->visible(false))->click();
        sleep(3);
        $tab->evaluate("//button[contains(@class, 'signin-button')]",
            EvaluateOptions::new()->timeout(1)->visible(false))->click();

        $loginOrVerify = $tab->evaluate("//input[contains(@name, 'username')] | //h1[contains(text(),'Verify Your Account')]");

        // Verify Your Account
        // We'll send you a 6-digit verification code.
        if ($loginOrVerify && strstr($loginOrVerify->getInnerText(), 'Verify Your Account')) {
            $tab->showMessage(Tab::identifyComputerMessage('Continue'));
            $errorOrTitle = $tab->evaluate("
            //div[contains(@class, 'account-info-display')]/descendant::div[contains(@class, 'member-points')]",
                EvaluateOptions::new()->timeout(180)->allowNull(true));
            if (!$errorOrTitle) {
                return LoginResult::identifyComputer();
            }
            if ($errorOrTitle->getNodeName() === 'DIV') {
                return new LoginResult(true);
            }
        }

        $login = $tab->evaluate("//input[contains(@name, 'username')]");
        $login->setValue($credentials->getLogin());
        $password = $tab->evaluate("//input[contains(@name, 'password')]");
        $password->setValue($credentials->getPassword());
        $captcha = $tab->evaluate('//img[@alt="captcha"]', EvaluateOptions::new()->allowNull(true)->timeout(2));
        if ($captcha) {
            $tab->showMessage('In order to log in into this account, you need to solve the CAPTCHA below and click the "Continue" button. Once logged in, sit back and relax, we will do the rest.');
        } else {
            $tab->evaluate("//button[contains(normalize-space(), 'Continue')]")->click();
        }


        $result = $tab->evaluate("
        //button[contains(@value, 'pick-authenticator')] 
        | //span[@class='ulp-input-error-message' and normalize-space()!='' and not(contains(.,'Solve the challenge question to verify you are not a robot.'))]
        ", EvaluateOptions::new()->timeout(90));

        if ($result->getAttribute('class') == 'ulp-input-error-message') {
            $this->logger->error("[error]: {$result->getInnerText()}");
            // We were unable to verify the information you provided. Please try again or visit Forgot Password to access your account.
            if (strstr($result->getInnerText(), 'We were unable to verify the information you provided.')) {
                return LoginResult::invalidPassword($result->getInnerText());
            }
        }
        else if ($result->getNodeName() === 'BUTTON') {
            $errorOrTitle = $tab->evaluate("
            //div[contains(@class, 'account-info-display')]/descendant::div[contains(@class, 'member-points')]
            | //h1[contains(text(),'Verify Your Account')]");

            if (stristr($errorOrTitle->getInnerText(), 'Verify Your Account')) {
                $tab->showMessage(Tab::identifyComputerMessage("Continue"));
                $errorOrTitle = $tab->evaluate("//div[contains(@class, 'account-info-display')]/descendant::div[contains(@class, 'member-points')]",
                    EvaluateOptions::new()->timeout(180)->allowNull(true));
                if (!$errorOrTitle) {
                    return LoginResult::identifyComputer();
                }
            }

            if ($errorOrTitle->getNodeName() === 'DIV') {
                //$tab->gotoUrl("https://www.wyndhamhotels.com/wyndham-rewards/my-account");
                return new LoginResult(true);
            }
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();

        $options = [
            'method' => 'get',
            'headers' => $this->headers
        ];
        $profile = $tab->fetch("https://www.wyndhamhotels.com/WHGServices/loyalty/V4/member/profile?includeProfile=true&includeAddresses=true&includeCurrencies=true&includeAliases=true&includePreferences=true&includeRedemptions=true&includeTiers=true&includePointExpiry=true&includePaymentCardAccounts=true",
            $options)->body;

        $this->logger->info($profile);
        $profile = json_decode($profile);

        if (isset($profile->currencies)) {
            foreach ($profile->currencies as $currency) {
                if ($currency->typeCode == 'POINTS') {
                    $st->setBalance($currency->available);
                }
            }
        } else {
            // Balance - You have ... Points
            $balance = $tab->evaluate('//div[@class="dashboard-account-summary "]//span[@data-binding="AccountInfo.PointBalance"]',
                EvaluateOptions::new()->timeout(15)->allowNull(true)->nonEmptyString());
            if ($balance) {
                $st->setBalance($balance->getInnerText());
            } else {
                $tab->saveScreenshot();
                $tab->saveHtml();
            }
        }
        // Name
        $firstName = $profile->firstName;
        $lastName = $profile->lastName;
        $st->addProperty("Name", beautifulName($firstName . ' ' . $lastName));
        // Status
        $st->addProperty('Status', $profile->currentTier->description ?? null);
        // MemberSince December 29, 2006
        $st->addProperty('MemberSince', date('F j, Y', strtotime($profile->enrollmentDateTime, false)));
        // Member #
        $st->setNumber($profile->accountNumber ?? null);
        // Nights to Next Level
        if (isset($profile->earningTier->accruedAmount, $profile->earningTier->requiredAmount)) {
            $st->addProperty('NextLevel', $profile->earningTier->requiredAmount - $profile->earningTier->accruedAmount);
        }
        // Nights
        if (isset($profile->earningTier->accruedAmount))
            $st->addProperty('QualifyingNights', $profile->earningTier->accruedAmount ?? null);

        // Expiration Date
        if (isset($profile->AccountInfo->PointExpirationInfo->PointExpirationBuckets)) { // TODO
            $expirationList = $profile->AccountInfo->PointExpirationInfo->PointExpirationBuckets;

            if (!empty($expirationList)) {
                $this->logger->notice("Set Exp date");

                foreach ($expirationList as $expiration) {
                    if (isset($expiration->Points) && $expiration->Points > 0 && (!isset($exp) || $exp > strtotime($expiration->ExpirationDate))) {
                        $st->addProperty('ExpiringBalance', $expiration->Points);
                        $exp = strtotime($expiration->ExpirationDate);
                        $st->setExpirationDate($exp);
                    }
                }
            }
            /**
             * https://redmine.awardwallet.com/issues/14300#note-11.
             *
             * In addition, after 18 consecutive months without any account activity,
             * all of your points will be forfeited.
             *
             * Be sure to stay or redeem with us by ... .
             */
            if (!empty($profile->CustLoyalty)) {
                foreach ($profile->CustLoyalty as $loyalty) {
                    if (in_array($loyalty->ProgramID, ['WVO', 'CET'])) {
                        continue;
                    }

                    if ($loyalty->ProgramID != 'WR') {
                        $this->notificationSender->sendNotification('refs #14300: Need to check Expiration Date // MI');
                    }

                    if (isset($exp, $loyalty->ExpireDate) && $exp > strtotime($loyalty->ExpireDate)) {
                        $this->logger->notice("Correcting Exp date");
                        $st->addProperty('ExpiringBalance', null);
                        $st->setExpirationDate(strtotime($loyalty->ExpireDate));
                    }
                }
            }
        }
    }

    public function parseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        $options = [
            'method' => 'get',
            'headers' => $this->headers
        ];
        $reservations = $tab->fetch("https://www.wyndhamhotels.com/WHGServices/loyalty/V4/member/reservations",
            $options)->body;
        $this->logger->info($reservations);
        if ($this->findPreg('/\{"success":"true","data":\[]}/', $reservations)) {
            $master->setNoItineraries(true);
            return;
        }
        $reservations = json_decode($reservations);
        $this->logger->debug("Found " . count($reservations->body->reservations->all) . " itineraries");

        $noPastItineraries = false;

        if (isset($reservations->body->reservations->all)) {
            foreach ($reservations->body->reservations->all as $item) {
                $date = strtotime($item->bookingDate);

                if (!$parseItinerariesOptions->isParsePastItineraries() && isset($date) && $date < time() && $item->status != 'Cancelled') {
                    $this->logger->debug('skip past reservation: ' . $item->confirmationNumber);
                    $noPastItineraries = true;

                    continue;
                }
                $this->ParseItinerary($master, $item, $reservations->body->property, $item->status == 'Cancelled');
            }
        }

        if (count($master->getItineraries()) === 0 && $noPastItineraries) {
            $master->setNoItineraries(true);
        }
    }

    public function ParseItinerary(Master $master, $item, $property, $cancelled = false)
    {
        $this->logger->notice(__METHOD__);

        if (empty($item->rooms)) {
            return;
        }
        // Rooms
        $rooms = $item->rooms;

        if (!isset($rooms->brandId, $property->{$rooms->brandId . $rooms->propertyCode})) {
            $this->logger->error('Property Not Found');

            return;
        }
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$item->confirmationNumber}", ['Header' => 3]);
        $this->currentItin++;
        // Hotels
        $hotel = $property->{$rooms->brandId . $rooms->propertyCode};

        $checkIn = $rooms->checkInDate . ' ' . ($hotel->checkInTime == 'NA' ? '' : $hotel->checkInTime);
        $checkOut = $rooms->checkOutDate . ' ' . ($hotel->checkOutTime == 'NA' ? '' : $hotel->checkOutTime);

        if ($checkIn == $checkOut) {
            $this->logger->error('Skip: invalid dates');

            return;
        }
        $h = $master->createHotel();

        if (!$cancelled) {
            $tax = round($rooms->totalTaxAmount, 2);

            if ($tax > 0) {
                $h->price()
                    ->tax($tax)
                    ->cost(round($rooms->roomRevenue, 2))
                    ->total(round($tax + $rooms->roomRevenue, 2))
                    ->currency($hotel->currency->code ?? null);
            }

            if (isset($rooms->pointsUsed) && $rooms->pointsUsed > 0) {
                $h->price()->spentAwards(number_format($rooms->pointsUsed) . ' PTS');
            }
        }

        $h->general()
            ->confirmation($item->confirmationNumber, "Confirmation Number", true)
            ->date2($item->bookingDate)
            ->status($item->status)
            ->traveller(beautifulName($rooms->firstName . " " . $rooms->lastName), true);
//            ->cancellation($this->http->FindSingleNode('(//div[normalize-space(text()) = "Cancellation policy"]/following-sibling::div[1])[1]'));

        if ($cancelled) {
            if ($item->status !== 'Cancelled') {
                $this->notificationSender->sendNotification('check parse cancelled. wrong status? // MI');
            } else {
                $h->general()
                    ->cancellationNumber($item->cancellationNumber)
                    ->cancelled();
            }
        }

        $address = join(', ', array_filter([
            $hotel->propertyAddress,
            $hotel->propertyCity,
            $hotel->propertyPostalCode,
            $hotel->propertyCountryCode,
        ]));
        $h->hotel()
            ->name($hotel->propertyName)
            ->phone($hotel->phone)
            ->address($address);

        if ($cancelled) {
            $h->booked()
                ->checkIn2($rooms->checkInDate)
                ->checkOut2($rooms->checkOutDate);
        } else {
            $h->booked()
                ->checkIn2($checkIn)
                ->checkOut2($checkOut)
                ->guests($rooms->noOfAdults)
                ->kids($rooms->noOfChildren)
                ->rooms(intval($rooms->noOfRooms));

            if ($h->getCheckOutDate() < $h->getCheckInDate()) {
                $master->removeItinerary($h);

                return;
            }

            if ($rooms->noOfRooms > 1) {
                $this->notificationSender->sendNotification("Multiple room were found // MI");
            }

//        $deadline = $this->http->FindSingleNode('(//div[normalize-space(text()) = "Cancellation policy"]/following-sibling::div[1])[1]/div[contains(text(), "Free cancellation until")]', null, true, "/until([^\(]+)/");
//        if ($deadline) {
//            $this->logger->debug($deadline);
//            $deadline = str_replace(',', '', $deadline);
//            $this->logger->debug($deadline);
//            $h->booked()->deadline2($deadline);
//        }
//        if ($this->http->FindSingleNode('(//div[normalize-space(text()) = "Cancellation policy"]/following-sibling::div[1])[1]/div[contains(text(), "Non-refundable reservation")]', null, false)) {
//            $h->booked()->nonRefundable();
//        }
            $r = $h->addRoom();
//        $r->setType();
            $r->setRate($rooms->roomRate, true);

            if (!strstr($rooms->rateDesc,
                'Prices quoted are only valid and available for Guests staying exclusively for leisure purposes, and shall not apply to those Guests staying for group, business, incentive, or meeting reasons.')
            ) {
                $r->setRateType($rooms->rateDesc, true);
            }
            $r->setDescription($rooms->roomDesc, true);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    public function parseHistory(
        Tab $tab,
        Master $master,
        AccountOptions $accountOptions,
        ParseHistoryOptions $historyOptions
    ): void {
        $statement = $master->getStatement();
        if (empty($statement->getNumber())) {
            $this->logger->error("AccountNumber not found");

            return;
        }
        $startDate = $historyOptions->getStartDate();
        $this->logger->debug('[History start date: ' . ($startDate ? $startDate->format('Y/m/d H:i:s') : 'all') . ']');

        if (isset($startDate)) {
            $startDate = $startDate->format('U');
        } else {
            $startDate = 0;
        }
        $options = [
            'method' => 'get',
            'headers' => $this->headers
        ];
        $activity = $tab->fetch("https://www.wyndhamhotels.com/WHGServices/loyalty/V4/member/activity?memberIdentifier=" . $statement->getNumber(),
            $options)->body;
        $this->logger->info($activity);
        $activity = json_decode($activity);
        $this->ParsePageHistory($statement, $startDate, $activity);
    }

    public function parsePageHistory(Statement $statement, $startDate, $response)
    {
        $result = [];

        if (empty($response->data)) {
            return $result;
        }
        $this->logger->debug("Total " . count($response->data) . " activity rows were found");

        foreach ($response->data as $activity) {
            $dateStr = $activity->transactionGroupDateTime;
            $date = strtotime($dateStr, false);

            if (isset($startDate) && $date < $startDate) {
                $this->logger->notice("break at date {$dateStr} ({$date})");

                break;
            }

            $points = 0;
            $miles = 0;

            foreach ($activity->transactionGroupEarn as $transactionGroupEarn) {
                if (!isset($transactionGroupEarn->currencyCategoryCode)) {
                    continue;
                }

                switch ($transactionGroupEarn->currencyCategoryCode) {
                    case 'Points':
                        $points = $transactionGroupEarn->amount;

                        break;

                    default:
                        $this->logger->debug("[currencyCategoryCode]: {$transactionGroupEarn->currencyCategoryCode}");

                        if ($transactionGroupEarn->currencyTypeCode == 'MILES') {
                            $miles = $transactionGroupEarn->amount;
                        }
                }
            }

            $result = [
                'Date'          => $date,
                'Description'   => $activity->stay[0]->ace03Description ?? $activity->transactionGroupDescription,
                'Activity Type' => $activity->translatedType,
                'Nights'        =>
                // Stay
                    $activity->stay[0]->eligibleNights
                    // Redemption
                    ?? $activity->transactions[0]->spend->quantity
                        // default
                        ?? 0,
                'Points'        => $points,
                'Miles'         => $activity->miles ?? $miles,
            ];
            $statement->addActivityRow($result);
        }

        return $result;
    }
}
