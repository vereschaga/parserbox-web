<?php

namespace AwardWallet\Engine\iberia;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ActiveTabInterface;
use AwardWallet\ExtensionWorker\ConfNoOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\ElementNotFoundException;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithConfNoInterface;
use AwardWallet\ExtensionWorker\LoginWithConfNoResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseHistoryInterface;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\RetrieveByConfNoInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Common\Statement;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;
use Exception;
use function AwardWallet\ExtensionWorker\beautifulName;

class IberiaExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseHistoryInterface, ParseItinerariesInterface, LoginWithConfNoInterface, RetrieveByConfNoInterface, ActiveTabInterface
{
    use TextTrait;

    private int $stepItinerary = 0;
    private array $headers = [
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.iberia.com/us/en/mi-iberia/#/IBPHOM/";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@id="loginPage:theForm:loginEmailInput"] | //a[@title="My profile"]/picture/img',
            EvaluateOptions::new()->timeout(30));
        return strtoupper($result->getNodeName()) === 'IMG';
    }

    public function getLoginId(Tab $tab): string
    {
        $accessToken = $tab->getCookies()['IBERIACOM_SSO_ACCESS'] ?? null;
        $accessTokenSalesforce = $tab->getCookies()['IBERIACOM_SSO_ACCESS_SALESFORCE'] ?? null;
        $options = [
            'mode' => 'cors',
            'credentials' => 'same-origin',
            'method' => 'get',
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'X-Salesforce-Token' => $accessTokenSalesforce,
            ],
        ];
        $data = $tab->fetch('https://miiberia.services.aws.iberia.com/iberia-plus/v1/iberia-plus/user-account',
            $options)->body;
        $this->logger->info($data);
        $data = json_decode($data);
        return preg_replace('/^0+/', '', $data->data->account->frequent_flyer_number ?? '');
    }

    public function logout(Tab $tab): void
    {

        $tab->gotoUrl('https://www.iberia.com/us/');
        $tab->evaluate('//span[@id="loggedUserName"]/ancestor::p[@title="User"]')->click();
        $tab->evaluate('//p[@title="Log out"]')->click();
        $tab->evaluate('//a[@title="Log in to Iberia Plus"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@id="loginPage:theForm:loginEmailInput"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="loginPage:theForm:loginPasswordInput"]')->setValue($credentials->getPassword());
        sleep(1);
        $tab->evaluate('//input[@id="loginPage:theForm:loginSubmit"]')->click();

        $result = $tab->evaluate('
                //*[contains(@id,"userErrorController")]
                | //a[@title="My profile"]/picture/img
            ', EvaluateOptions::new()->timeout(40));
        $this->logger->notice("[RESULT NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");


        if (str_starts_with($result->getInnerText(), "Login has failed. Some of the details you entered may be incorrect")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        if (strtoupper($result->getNodeName()) === 'IMG') {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $options): void
    {
        sleep(1);
        $accessToken = $tab->getCookies()['IBERIACOM_SSO_ACCESS'] ?? null;
        $accessTokenSalesforce = $tab->getCookies()['IBERIACOM_SSO_ACCESS_SALESFORCE'] ?? null;
        $options = [
            'mode' => 'cors',
            'credentials' => 'same-origin',
            'method' => 'get',
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'X-Salesforce-Token' => $accessTokenSalesforce,
            ],
        ];
        $data = $tab->fetch('https://miiberia.services.aws.iberia.com/iberia-plus/v1/iberia-plus',
            $options)->body;
        $this->logger->info($data);
        $data = json_decode($data);
        $st = $master->createStatement();
        // Avios balance
        $st->setBalance($data->data->avios->account->avios);
        // Iberia Plus number
        $st->setNumber(preg_replace('/^0+/', '', $data->data->avios->account->frequent_flyer_number));
        // Level
        $st->addProperty('Level', beautifulName($data->data->avios->account->level));
        // Valid until
        if (!empty($data->data->avios->account->level_validity_date)) {
            $st->addProperty('CardExpiry',
                date('d/m/Y', strtotime($data->data->avios->account->level_validity_date)));
        }
        //$st->addProperty('Since', $account->data->account->);
        // Name
        $st->addProperty('Name', beautifulName("{$data->data->avios->account->name} {$data->data->avios->account->first_surname}"));
        // Lifetime Elite Points
        $st->addProperty('LifetimeElitePoints', $data->data->avios->account->elite);
        // Bonus Iberia Plus
        $st->addProperty('ElitePoints', $data->data->avios->account->level_status->elite->current_points);
        // Flights
        $st->addProperty('Flights', $data->data->avios->account->level_status->flights->current_flights ?? null);
    }


    public function parseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        $this->logger->notice(__METHOD__);
        $accessToken = $tab->getCookies()['IBERIACOM_SSO_ACCESS'] ?? null;
        $accessTokenSalesforce = $tab->getCookies()['IBERIACOM_SSO_ACCESS_SALESFORCE'] ?? null;
        $options = [
            'mode' => 'cors',
            'credentials' => 'same-origin',
            'method' => 'get',
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'X-Salesforce-Token' => $accessTokenSalesforce,
            ],
        ];
        $trips = $tab->fetch('https://miiberia.services.aws.iberia.com/my-trips/v2/my-trips/upcoming?offset=0&limit=50',
            $options)->body;
        $this->logger->info($trips);
        if ($this->findPreg('/"results":\s*\[],"query"/', $trips)) {
            $master->setNoItineraries(true);
            return;
        }
        $trips = json_decode($trips);

        foreach ($trips->data->results as $trip) {
//            $tab->gotoUrl("https://www.iberia.com/us/manage-my-booking/?pnr=$trip->pnr_locator&surname=$trip->last_name");
//            $tab->evaluate('//h1[contains(text(),"My Trips")]', EvaluateOptions::new()->timeout(160));

            $this->retrieveConfNo($tab, $master,  $trip->pnr_locator, $trip->last_name);
            usleep(random_int(300, 1000));
        }
    }

    private function parseItinerary(Tab $tab, Master $master, $data)
    {
        $this->logger->notice(__METHOD__);

        $f = $master->add()->flight();
        $confNo = $data->order->bookingReferences[0]->reference;
        $this->logger->info(sprintf("[%s] Parse Itinerary #%s", $this->stepItinerary++, $confNo), ['Header' => 3]);
        $f->general()->confirmation($confNo);

        foreach ($data->passengers as $passenger) {
            $f->general()->traveller(beautifulName("{$passenger->personalInfo->name} {$passenger->personalInfo->surname}"));
        }

        if (isset($data->tickets)) {
            $ticketsArr = [];

            foreach ($data->tickets as $tickets) {
                foreach ($tickets->ticketNumbers as $ticket) {
                    $this->logger->debug(var_export($ticket, true));
                    $ticketsArr[] = $ticket;
                }
            }
            $this->logger->debug(var_export($ticketsArr, true));

            $f->issued()->tickets(array_unique($ticketsArr), false);
        }

        foreach ($data->order->slices as $slice) {
            foreach ($slice->segments as $seg) {
                $s = $f->addSegment();
                $s->airline()->name(!empty($seg->flight->operationalCarrier->code) ? $seg->flight->operationalCarrier->code : $seg->flight->marketingCarrier->code);
                $s->airline()->number(!empty($seg->flight->operationalFlightNumber) ? $seg->flight->operationalFlightNumber : $seg->flight->marketingFlightNumber);
                $s->airline()->operator($seg->flight->operationalCarrier->name);

                $s->departure()->name($seg->departure->name);
                $s->departure()->code($seg->departure->code);
                $s->departure()->date2($seg->departureDateTime);
                $s->arrival()->name($seg->arrival->name);
                $s->arrival()->code($seg->arrival->code);
                $s->arrival()->date2($seg->arrivalDateTime);
                $s->extra()->cabin($seg->cabin->type, false, true);
                $s->extra()->bookingCode($seg->cabin->code, false, true);
                $s->extra()->aircraft($seg->flight->aircraft->description ?? null, false, true);
                $s->extra()->duration($this->convertMinsToHrsMins($seg->duration));

                $seats = [];

                foreach ($data->order->orderItems as $seat) {
                    if ($seat->type === 'seat' && $seg->id === $seat->segmentId) {
                        $seats[] = $seat->row . $seat->column;
                    }
                }
                $s->extra()->seats($seats);
            }
        }

        if (isset($data->order->price->total) && !empty($data->order->price->currency)) {
            $f->price()->total($data->order->price->total);
            $f->price()->currency($data->order->price->currency);
            $f->price()->tax($data->order->price->fare);
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function convertMinsToHrsMins($mins)
    {
        $h = floor($mins / 60);
        $m = round($mins % 60);
        $h = ($h < 10) ? ('0' . $h) : ($h);
        $m = ($m < 10) ? ('0' . $m) : ($m);

        return "{$h}:{$m}";
    }

    public function parseHistory(
        Tab $tab,
        Master $master,
        AccountOptions $accountOptions,
        ParseHistoryOptions $historyOptions
    ): void
    {
        $startDate = $historyOptions->getStartDate();
        $this->logger->debug('[History start date: ' . ($startDate ? $startDate->format('Y/m/d H:i:s') : 'all') . ']');
        $startDate = isset($startDate) ? $startDate->format('U') : 0;
        $statement = $master->getStatement();

        $accessToken = $tab->getCookies()['IBERIACOM_SSO_ACCESS'] ?? null;
        $accessTokenSalesforce = $tab->getCookies()['IBERIACOM_SSO_ACCESS_SALESFORCE'] ?? null;
        $options = [
            'mode' => 'cors',
            'credentials' => 'same-origin',
            'method' => 'get',
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'X-Salesforce-Token' => $accessTokenSalesforce,
            ],
        ];
        $movements = $tab->fetch('https://miiberia.services.aws.iberia.com/iberia-plus/v1/iberia-plus/avios-movements?offset=0&limit=150',
            $options)->body;
        $this->logger->info($movements);
        $movements = json_decode($movements);
        $this->parsePageHistory($statement, $movements->data->results, $startDate);
    }

    public function parsePageHistory(Statement $statement, $transactions, $startDate)
    {
        $this->logger->notice(__METHOD__);
        foreach ($transactions as $transaction) {
            $postDate = strtotime($transaction->date);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$transaction->date} ($postDate)");

                continue;
            }
            $statement->addActivityRow([
                'Date'         => $postDate,
                'Description'  => $transaction->description,
                'Sector'       => $transaction->sector,
                'Avios'        => $transaction->avios_increment,
                'Elite Points' => $transaction->elite_increment,
            ]);
        }
    }
    public function getLoginWithConfNoStartingUrl(array $confNoFields, ConfNoOptions $options): string
    {
        return 'https://www.iberia.com/us/manage-my-booking/?language=en&market=us&channel=COM#!/chktrp';
    }

    public function loginWithConfNo(Tab $tab, array $confNoFields, ConfNoOptions $options): LoginWithConfNoResult
    {
        $this->waitFor(function (Tab $tab) {
            return !$tab->evaluate('//*[@id="bbki-generic-wait-loading-elem"]',
                EvaluateOptions::new()->allowNull(true)->timeout(0));
        }, $tab, 60);
        $tab->evaluate('//input[@id="ANONYMOUS_LOGIN_INPUT_SURNAME"]')->setValue($confNoFields['LastName']);
        $tab->evaluate('//input[@id="ANONYMOUS_LOGIN_INPUT_PNR"]')->setValue($confNoFields['ConfNo']);
        $tab->evaluate('//input[@id="ANONYMOUS_LOGIN_BOTON"]')->click();
        $loginResult = $tab->evaluate('//h1[contains(text(), "Your trip to:")]
        | //p[contains(text(),"This booking code has not been found. Please check that it is correct and try again.")]');

        if ($loginResult) {
            $error = $loginResult->getInnerText();
            if (
                stristr($error, "This booking code has not been found. Please check that it is correct and try again.")
                || stristr($error,
                    "A general error has occurred. We apologise for the inconvenience. Please try again later.")
            ) {
                return LoginWithConfNoResult::error($error);
            }
        }

        $tab->saveScreenshot();
        $tab->saveHtml();
        return LoginWithConfNoResult::success();
    }
    public function retrieveByConfNo(Tab $tab, Master $master, array $fields, ConfNoOptions $options): void {
        $this->retrieveConfNo($tab, $master,  $fields['ConfNo'], $fields['LastName']);
    }

    private function retrieveConfNo(Tab $tab, Master $master, string $confNo, string $lastName): bool
    {
        $this->logger->notice(__METHOD__);
        $bookingInfo = $tab->getFromSessionStorage('ib-MmB-app.bookingInfo') ?? null;
            //$tab->getCookies()['IBERIACOM_SSO_ACCESS'] ?? null;

        if (!$bookingInfo) {
            return false;
        }

        /*$options = [
            'mode' => 'cors',
            //'credentials' => 'same-origin',
            'method' => 'post',
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                //'X-Salesforce-Token' => $accessTokenSalesforce,
                'Accept' => 'application/json, text/plain, * / *',
                'Accept-Language' => 'en-US',
                'Content-Type' => 'application/json;charset=UTF-8',
                'X-Observations-Current-Page' => 'null',
                'X-Observations-Origin-Page' => 'null',
                'X-Request-Appversion' => '10.33.1',
                'X-Request-Device' => 'unknown|chrome|122.0.0.0',
                'X-Request-Osversion' => 'linux|unknown',
            ],
            'body' => json_encode([
                'locator' => $confNo,
                'surname' => $lastName
            ])
        ];
        try {
            $import = $tab->fetch('https://ibisservices.iberia.com/api/sse-orm/rs/v2/order/import',
                $options)->body;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            sleep(3);
            try {
                $import = $tab->fetch('https://ibisservices.iberia.com/api/sse-orm/rs/v2/order/import',
                    $options)->body;
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $this->logger->error("Skip itinerary: #$confNo");
                return false;
            }
        }*/
        $this->logger->info($bookingInfo);
        $this->parseItinerary($tab, $master, json_decode($bookingInfo));
        return true;
    }

    public function waitFor($whileCallback, Tab $tab, $timeoutSeconds = 15)
    {
        $start = time();

        do {
            try {
                if (call_user_func($whileCallback, $tab)) {
                    return true;
                }
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
            sleep(1);
        } while ((time() - $start) < $timeoutSeconds);

        return false;
    }

    public function isActiveTab(AccountOptions $options): bool
    {
        return true;
    }
}
