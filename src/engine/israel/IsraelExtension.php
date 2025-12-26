<?php

namespace AwardWallet\Engine\israel;

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

class IsraelExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface
{
    use TextTrait;

    private int $stepItinerary = 0;
    private $headers = [
        'Accept' => 'application/json, text/plain, */*',
        'Adrum' => 'isAjax:true',
        'Content-Type' => 'application/json; charset=UTF-8'
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.elal.com/eng/frequentflyer/myffp/myaccount";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[contains(@name,"MembertxtID")] | //app-account-details//span[contains(text(),"Member Number:")]',
            EvaluateOptions::new()->timeout(30));
        return str_starts_with($result->getInnerText(), "Member Number:");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//app-account-details//span[contains(text(),"Member Number:")]',
            FindTextOptions::new()->preg('/:\s*(\d+)$/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//div[@class="account-innerBox-content"]')->click();
        $tab->evaluate('//button[@class="logout"]')->click();
        $tab->evaluate('//input[contains(@name,"MembertxtID")]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[contains(@name,"MembertxtID")]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[contains(@name,"PasswordtxtID")]')->setValue(substr($credentials->getPassword(), 0, 12));
        $tab->evaluate('//input[@type="submit" and @value="Sign In"]')->click();

        $result = $tab->evaluate('
            //span[contains(@id,"_MembertxtID-error")]
            | //span[contains(@id,"_PasswordtxtID-error")]
            | //span[contains(@id,"_ResponseErrorMsg")]
            | //app-account-details//span[contains(text(),"Member Number:")] 
        ', EvaluateOptions::new()->timeout(30));
        $this->logger->notice("[RESULT NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if (str_starts_with($result->getInnerText(), "Member And/Or Password Incorrect")
            || str_starts_with($result->getInnerText(), "Member Number Is Invalid")
            || str_starts_with($result->getInnerText(), "User or password are invalid!")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }

        if (str_starts_with($result->getInnerText(), "Member Number:")) {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {

        $st = $master->createStatement();
        // Points In Your Account
        $st->setBalance(str_replace(',', '', $tab->findText("//em[@class = 'balance']")));
        // Status
        $st->addProperty('CurrentClubStatus', $tab->findText("//em[@class = 'status']"));
        // Status valid until
        $statusExpiration = $tab->findText("//span[contains(text(), 'Status valid until')]/following-sibling::span",
            FindTextOptions::new()->allowNull(true));
        if (isset($statusExpiration))
            $st->addProperty('StatusExpiration', $statusExpiration);
        // Name
        $st->addProperty('Name',
            beautifulName($tab->findText("//div[contains(@class, 'inner-container')]//span[@class='name']")));
        // Member Number
        $st->addProperty('MemberNo',
            $tab->findText("//div[contains(@class, 'inner-container')]//span[contains(text(), 'Member Number')]",
                FindTextOptions::new()->preg("/:\s*(\d+)/i")));
        // Diamonds for next Tier
        $st->addProperty('DiamondsForNextTier',
            $tab->findText("(//p[contains(text(), 'To upgrade to')])[1]/preceding-sibling::div//div/span/b"));
        // Flight segments for next Tier
        $st->addProperty('FlightForNextTier',
            $tab->findText("(//p[contains(text(), 'To upgrade to')])[2]/preceding-sibling::div[2]//div/span/b"));
        // Diamonds to maintain Tier
        $diamondsToMaintainTier = $tab->findText("(//p[contains(text(), 'To maintain')])[1]/preceding-sibling::div//div/span/b",
            FindTextOptions::new()->allowNull(true));
        if (isset($diamondsToMaintainTier))
            $st->addProperty('DiamondsToMaintainTier', $diamondsToMaintainTier);
        // Flight segments to maintain Tier
        $flightToMaintainTier = $tab->findText("(//p[contains(text(), 'To maintain')])[2]/preceding-sibling::div[2]//div/span/b",
            FindTextOptions::new()->allowNull(true));
        if (isset($flightToMaintainTier))
            $st->addProperty('FlightToMaintainTier', $flightToMaintainTier);
    }


    public function parseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        $this->logger->notice(__METHOD__);
        $token = str_replace('"', '', $tab->getFromSessionStorage('tokenElal'));
        $options = [
            'method' => 'get',
            'headers' => $this->headers + [
                    "Referer" => "https://www.elal.com/eng/frequentflyer/myffp/myflights",
                    'Authorization' => "Bearer $token"
                ],
        ];
        try {
            $future = $tab->fetch('https://www.elal.com/api/MyFlights/futureFlights/lang/eng', $options)->body;
            $this->logger->info($future);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return;
        }

        if ($this->findPreg('/"routesCounter":0,"routes":\[]/', $future)) {
            $master->setNoItineraries(true);
            return;
        }

        $itineraries = [];
        $futureFlights = json_decode($future)->utureFlights ?? [];
        $routes = $futureFlights->routes ?? [];
        $this->logger->debug("Total " . count($routes) . " routes were found");

        foreach ($routes as $item) {
            if (!isset($itineraries[$item->pnr])) {
                $itineraries[$item->pnr] = $item->departure_Date;
            }
        }
        $this->logger->info(sprintf('Total %s itineraries were found', count($itineraries)));

        $token = str_replace('"', '', $tab->getFromSessionStorage('tokenElal'));
        $options = [
            'method' => 'get',
            'headers' => $this->headers + [
                    "Referer" => "https://www.elal.com/eng/frequentflyer/myffp/myflights",
                    'Authorization' => "Bearer $token"
                ],
        ];
        foreach ($itineraries as $pnr => $departureDate) {
            try {
                $url = $tab->fetch("https://www.elal.com/api/MyFlights/manageOrderLink/lang/eng/pnr/{$pnr}",
                    $options)->body;
                $this->logger->info($url);
                $tab->gotoUrl(str_replace('"', '', $url));
                sleep(2);
                $enc = $this->findPreg('/\?enc=(.+?)&/', $url);
                if ($enc) {
                    $token = str_replace('"', '', $tab->getFromSessionStorage('sessionId'));
                    $options['headers']['Authorization'] = "Bearer $token";
                    $data = $tab->fetch("https://booking.elal.com/bfm/service/extly/retrievePnr/secured/manageMyBooking?enc=$enc",
                        $options)->body;
                    $this->logger->info($data);
                    $this->parseItinerary($tab, $master, json_decode($data));
                }
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                break;
            }

            //$this->parseItinerary($tab, $master, $data);
        }
    }

    private function parseItinerary(Tab $tab, Master $master, $data)
    {
        $this->logger->notice(__METHOD__);
        $f = $master->add()->flight();
        $confNo = $data->data->bookingSummary->booking->reference;
        $this->logger->info(sprintf("[%s] Parse Itinerary #%s", $this->stepItinerary++, $confNo), ['Header' => 3]);
        $f->general()
            ->confirmation($confNo, "Booking code", true);

        foreach ($data->data->bookingSummary->booking->passengers as $passenger) {
            $f->general()->traveller(beautifulName("{$passenger->firstName} {$passenger->lastName}"));
            $f->program()->account($passenger->matMidNumber, false);
        }

        foreach ($data->data->bookingSummary->booking->tripNew ?? [] as $trip) {
            foreach ($trip->bound->segments ?? [] as $seg) {
                $s = $f->addSegment();
                $s->airline()
                    ->name($seg->airline->name)
                    ->number($seg->flightNumber);

                $s->departure()
                    ->code($seg->departureAirport->code)
                    ->name($seg->departureAirport->label)
                    ->terminal($seg->departureTerminal->name, true)
                    ->date2($this->findPreg('/^(\d{4}-.+?\d+:\d+)/', $seg->departureDate));

                $s->arrival()
                    ->code($seg->arrivalAirport->code)
                    ->name($seg->arrivalAirport->label)
                    ->terminal($seg->arrivalTerminal->name, true)
                    ->date2($this->findPreg('/^(\d{4}-.+?\d+:\d+)/', $seg->arrivalDate));


                $s->extra()
                    ->meals($seg->mealTypes)
                    ->aircraft($seg->aircraftType)
                    ->stops(array_sum($seg->stops));

                foreach ($seg->fares as $fare) {
                    $s->extra()->bookingCode($fare->rbd);
                    $s->extra()->cabin($fare->cabinTypeName);
                }

                $hours = floor($seg->duration / 60 / 60);
                $minutes = $seg->duration / 60 % 60;
                $s->extra()->duration($hours > 0 ? sprintf('%02dh %02dm', $hours, $minutes) : sprintf('%02dm', $minutes));
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return [];
    }

    private function parseItineraryHtml(Tab $tab, Master $master, $url)
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl($url);
        $r = $master->add()->flight();

        $tab->evaluate('(//div[starts-with(normalize-space(),"Booking code")]/following-sibling::div)[1]');
        $pnrs = array_unique($tab->findTextAll("//div[starts-with(normalize-space(),'Booking code')]/following-sibling::div[1]"));

        if (count($pnrs) !== 1) {
            $this->logger->notice("check booking code");

            return;
        }

        $r->general()
            ->confirmation(array_shift($pnrs), 'Booking Code')
            ->travellers($tab->findTextAll("//div[normalize-space()='E-ticket itinerary receipt']/following-sibling::div[1]/descendant::text()[normalize-space()!=''][1]"));
        $r->issued()
            ->tickets($tab->findTextAll("//div[normalize-space()='E-ticket number:']/following-sibling::div[1]"),
                false);
        $r->program()
            ->accounts($tab->findTextAll("//div[normalize-space()='Aeroflot Bonus:']/following-sibling::div[1]"),
                false);

        $phones = $tab->evaluateAll("(//div[normalize-space()='Contact details:'])[1]/following-sibling::div[div[@class='text-bold']]");
        $this->logger->debug('Phones: ' . count($phones));

        foreach ($phones as $item) {
            $phone = $tab->findText("./div[@class='text-bold']", FindTextOptions::new()->contextNode($item));
            $text = $tab->findText("./following-sibling::div[1]", FindTextOptions::new()->contextNode($item));
            $r->program()->phone($phone, $text);
        }

        $sums = $tab->findTextAll("//div[normalize-space()='Amount paid and payment method']/following-sibling::div[normalize-space() and not(contains(.,'***'))][1]");
        $total = 0.0;
        $currency = null;

        foreach ($sums as $sum) {
            $currency = $this->findPreg("/^([A-Z]{3})\s*\d[\d.]+$/", $sum);
            $total += \AwardWallet\Common\Parser\Util\PriceHelper::cost($this->FindPreg("/(?:[A-Z]{3})\s*(\d[\d.]+)$/",
                $sum));
        }
        $r->price()
            ->total($total)
            ->currency($currency);

        $segments = $tab->evaluateAll("(//div[normalize-space()='Itinerary']//ancestor::div[1])[1]/div[contains(@class,'route__flight')]");

        foreach ($segments as $segment) {
            $s = $r->addSegment();
            $flight = $tab->findText(".//span[@class='route__flight-number-data']",
                FindTextOptions::new()->contextNode($segment));
            $depDate = $tab->findText(".//div[contains(@class,'time-destination__date time-destination__date--left')]",
                FindTextOptions::new()->contextNode($segment));
            $depTime = $tab->findText(".//div[contains(@class,'time-destination__from')]/descendant::text()[normalize-space()!=''][1]",
                FindTextOptions::new()->contextNode($segment));
            $this->logger->debug("DepDate: $depDate, $depTime");

            if (empty($depDate) || empty($depTime)) {
                return;
            }
            $class = $tab->findText(".//span[normalize-space()='Class:']/following-sibling::span[1]",
                FindTextOptions::new()->contextNode($segment));
            $s->airline()
                ->name($this->findPreg("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/", $flight))
                ->number($this->findPreg("/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", $flight));
            $s->departure()
                ->name($tab->findText(".//div[@class='route__flight-city-from']",
                    FindTextOptions::new()->contextNode($segment)))
                ->date(strtotime($depTime, strtotime($depDate)))
                ->code($tab->findText(".//div[contains(@class,'time-destination__from')]/descendant::text()[normalize-space()!=''][2]",
                    FindTextOptions::new()->contextNode($segment)->preg('/^[A-Z]{3}/')));
            $s->arrival()
                ->name($tab->findText(".//div[@class='route__flight-city-to']",
                    FindTextOptions::new()->contextNode($segment)))
                ->code($tab->findText(".//div[contains(@class,'time-destination__to')]/descendant::text()[normalize-space()!=''][1]",
                    FindTextOptions::new()->contextNode($segment)->preg('/^[A-Z]{3}/')));

            $arrDate = $tab->findText(".//div[contains(@class,'time-destination__date time-destination__date--right')]",
                FindTextOptions::new()->contextNode($segment));
            $arrTime = $tab->findText(".//div[contains(@class,'time-destination__to')]/descendant::text()[normalize-space()!=''][2]",
                FindTextOptions::new()->contextNode($segment));
            $this->logger->debug("ArrDate: $arrDate, $arrTime");

            if (!empty($arrDate) && !empty($arrTime)) {
                $s->arrival()->date(strtotime($arrTime, strtotime($arrDate)));
            } elseif (trim($tab->findText(".//div[contains(@class,'time-destination__to')]/div[contains(@class,'time-destination__time')]",
                    FindTextOptions::new()->contextNode($segment))) == '') {
                $s->arrival()->noDate();
            }
            $s->extra()
                ->status($tab->findText(".//span[normalize-space()='Status:']/following-sibling::span[1]",
                    FindTextOptions::new()->contextNode($segment)))
                ->cabin($this->findPreg("/^(.+)\s*\/\s*[A-Z]{1,2}$/", false, $class))
                ->bookingCode($this->findPreg("/^.+\s*\/\s*([A-Z]{1,2})$/", false, $class));
        }

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

}
