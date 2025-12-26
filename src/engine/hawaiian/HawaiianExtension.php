<?php

namespace AwardWallet\Engine\hawaiian;

use AwardWallet\Common\Parsing\Exception\ProfileUpdateException;
use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ElementNotFoundException;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\ParseHistoryInterface;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use AwardWallet\Schema\Parser\Common\Statement;
use function AwardWallet\ExtensionWorker\beautifulName;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class HawaiianExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface, ParseHistoryInterface
{
    use TextTrait;

    private array $milageActivityDetails = [];
    private $stepItinerary = 1;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.hawaiianairlines.com/my-account#/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $loginFieldOrNumber = $tab->evaluate('//span[@id="member_number"] | //form[@id="login"] | //span[@id="my_account_user_details_dropdown"] | //div[@id="alt-user-menu"]', EvaluateOptions::new()->timeout(30));

        return in_array($loginFieldOrNumber->getAttribute('id'), ['member_number', 'my_account_user_details_dropdown', 'alt-user-menu']);
    }

    public function getLoginId(Tab $tab): string
    {
        $accountNumber = $tab->findText('//span[@id="member_number"]', FindTextOptions::new()->nonEmptyString()->preg('/^(\d+)$/'));
        $this->logger->debug("Account Number: $accountNumber");

        return $accountNumber ?? '';
    }

    public function logout(Tab $tab): void
    {
        $tab->gotoUrl('https://www.hawaiianairlines.com/MyAccount/Login/SignOut?area=');
        $result = $tab->evaluate('//form[@id="login"] | //a[contains(@href,"/my-account/login")]', EvaluateOptions::new()->timeout(60));
        if (strtoupper($result->getNodeName()) == 'A') {
            $result->click();
            $tab->evaluate('//form[@id="login"]//input[@name="UserName"]');
        }
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $login = $tab->evaluate('//form[@id="login"]//input[@name="UserName"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//form[@id="login"]//input[@name="Password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//form[@id="login"]//button[@id="submit_login_button"]')->click();

        $errorOrNumber = $tab->evaluate($xpath = '
            //div[@class="sign-in-form"]//div[contains(@class,"alert-content")]/div[contains(@class,"alert-content-primary")] 
            | //span[@id="my_account_user_details_dropdown" and contains(text(),"Miles Balance")]
            | //h4[contains(text(), "Current Balance")]
            | //div[contains(text(),"Update your Security Questions to access your HawaiianMiles dashboard!")]
            | //h1/em[contains(text(),"Book a Flight")]');
        $message = $errorOrNumber->getInnerText();

        // TODO: Incorrect redirect after authorization
        if (stripos($message, 'Book a Flight') !== false) {
            $tab->gotoUrl('https://www.hawaiianairlines.com/my-account');
            $errorOrNumber = $tab->evaluate($xpath);
            $message = $errorOrNumber->getInnerText();
        }

        if (stripos($message, 'Miles Balance') !== false || stristr($message, 'Current Balance')) {
            $this->logger->notice('logged in');
            $tab->gotoUrl('https://www.hawaiianairlines.com/my-account');
            return new LoginResult(true);
        } elseif (stristr($message, 'Update your Security Questions to access your HawaiianMiles')) {
            throw new ProfileUpdateException();
        } elseif (stristr($message, 'Email and password could not be found. Please try again.')) {
            return LoginResult::invalidPassword($message);
        } else {
            $this->logger->error('error logging in');
            return new LoginResult(false);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        // Balance - Current Point Balance
        $statement->setBalance(str_replace(',', '', $tab->findText('//span[@id="current-balance"]/@end-val', FindTextOptions::new()->preg('/^[\d.,]+$/'))));
        // Name
        $statement->addProperty('Name', beautifulName($tab->findText('//h2[@class="hamiles-logo-header"]/following-sibling::p[not(span)]')));
        // Number label:contains("Member Number") + span
        $statement->addProperty('Number', $tab->findText('//span[@id="member_number"]', FindTextOptions::new()->preg('/^(\w+)$/')));
        // Status h2.hamiles-logo-header + p + h3
        $statement->addProperty('Status', $tab->findText('//h2[@class="hamiles-logo-header"]/following-sibling::h3'));


        $tab->gotoUrl('https://www.hawaiianairlines.com/my-account/hawaiianmiles/mileage-statement');
        $tab->evaluate('//h1[contains(text(),"Mileage Statement")]');
        $json = $tab->findText('//script[contains(text(),"MileageStatementModelJson")]',
            FindTextOptions::new()->preg('/var MileageStatementModelJson\s*=\s*(.+?);\s*var milesAbbText/')->visible(false));
        //$this->logger->info($json);
        $json = json_decode($json);
        // Member since
        $statement->addProperty('MemberSince', $json->AccountInfo->MemberSince);
        // Prior Month's Balance
        $statement->addProperty('PriorBalance', $json->MileageSummary->PriorBalance);
        // Miles Credited this Month
        if ($json->MileageSummary->MilesCredited)
            $statement->addProperty('CreditedthisMonth', $json->MileageSummary->MilesCredited );
        // Miles Redeemed this Month
        if ($json->MileageSummary->MilesRedeemed)
        $statement->addProperty('RedeemedthisMonth', $json->MileageSummary->MilesRedeemed);
        // Qualifying Flight Miles
        $statement->addProperty('QualifyingFlightMiles', $json->MileageSummary->QualifyingMiles);
        // Qualifying Flight Miles
        $statement->addProperty('QualifyingFlightSegments', $json->MileageSummary->QualifyingSegments);

        $this->milageActivityDetails = $json->MilageActivityDetails ?? [];
    }

    public function parseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        $tab->gotoUrl('https://mytrips.hawaiianairlines.com/');
        $tab->evaluate('//h1[contains(text(),"My trips")] | //find-my-trip/div[contains(text(),"Find my trip")]');

        $this->parseItineraryStep($tab, $master);
        sleep(5);
    }

    private function parseItineraryStep(Tab $tab, Master $master) {
        $this->logger->notice(__METHOD__);
        try {
            $tab->evaluate("(//*[contains(@class,'trips__group')]//ha-button[contains(.,'View itinerary')])[$this->stepItinerary]",
                EvaluateOptions::new()->timeout(5))
                ->click();
        } catch (ElementNotFoundException $e) {
            $this->logger->notice('Finished collecting reservations');
            if ($this->stepItinerary == 1 && $tab->findTextNullable('//div[contains(text(),"You do not have any upcoming trips connected to your HawaiianMiles account.")]')) {
                $master->setNoItineraries(true);
            }
            return;
        }

        $this->parseItinerary($tab, $master);
        //$tab->gotoUrl('https://mytrips.hawaiianairlines.com/');
        $tab->back();
        $tab->evaluate('//h1[contains(text(),"My trips")]');
        $this->parseItineraryStep($tab, $master);
    }

    private function parseItinerary(Tab $tab, Master $master)
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//h1[contains(text(),"Your itinerary")]');
        $json = json_decode(json_decode($tab->getFromSessionStorage('state')));
        foreach ($json->tripState->trip->results as $trip) {
            $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->stepItinerary++, $trip->confirmationCode), ['Header' => 3]);
            //$h->general()->confirmation($booking->bookNumber);
            $f = $master->createFlight();
            $f->general()->confirmation($trip->confirmationCode, 'Confirmation code');

            foreach ($trip->passengers->entries as $entry) {
                $f->general()->traveller(beautifulName("{$entry->passengerName->firstName} {$entry->passengerName->lastName}"));

                if (isset($entry->hawaiianMilesNumber)) {
                    $f->program()->account($entry->hawaiianMilesNumber, false);
                }
            }

            foreach ($trip->flights->entries as $entry) {
                $s = $f->addSegment();
                $s->airline()->name($entry->airlineCode ?? $entry->operatedBy);
                $s->airline()->number($entry->flightNumber);

                $s->departure()->date2($entry->scheduledDeparture->airportDateTimeString);
                $s->departure()->code($entry->origin);
                $s->arrival()->date2($entry->scheduledArrival->airportDateTimeString);
                $s->arrival()->code($entry->scheduledDestination);

                $s->extra()->aircraft($entry->aircraftTypeDescription);

                foreach ($trip->segments->entries as $segEntry) {
                    foreach ($segEntry->details as $detail) {
                        foreach ($detail->flightDetails as $flightDetail) {
                            if ($flightDetail->flightId == $entry->id) {
                                if (isset($flightDetail->seatNumber)) {
                                    $this->logger->debug('go to segment: ' . $entry->id);
                                    $s->extra()->seat($flightDetail->seatNumber);
                                }

                                break 2;
                            }
                        }
                    }
                }
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    public function parseHistory(Tab $tab, Master $master, AccountOptions $accountOptions, ParseHistoryOptions $historyOptions): void
    {
        $startDate = $historyOptions->getStartDate();
        $this->logger->debug('[History start date: ' . ($startDate ? $startDate->format('Y/m/d H:i:s') : 'all') . ']');
        if (isset($startDate)) {
            $startDate = $startDate->format('U');
        } else {
            $startDate = 0;
        }

        $statement = $master->getStatement();
        $this->parsePageHistory($statement, $startDate);
    }

    private function parsePageHistory(Statement $statement, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $history = $this->milageActivityDetails;
        $rows = count($history);
        $this->logger->debug("Total {$rows} history items were found");

        foreach ($history as $row) {
            $dateStr = $row->PostedDateDisplay;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result['Posted Date'] = $postDate;
            $result['Activity Date'] = strtotime($row->ActivityDateDisplay, false);
            $result['Description'] = $row->Description;
            $result['Status Eligible'] = $row->StatusMiles;
            $result['Segments'] = $row->Segments;
            $result['Miles'] = $row->Miles;
            $result['Bonus Miles'] = $row->BonusMiles;
            $result['Total Miles'] = $row->TotalMiles;
            $statement->addActivityRow($result);
        }
    }
}
