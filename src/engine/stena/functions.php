<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

class TAccountCheckerStena extends TAccountChecker
{
    use PriceTools;

    public $regionOptions = [
        ""        => "Select your region",
        "Germany" => "Germany",
        "UK"      => "United Kingdom",
    ];

    private $domain = 'co.uk';
    private $subDomain = 'booking';
    private $converter;
    private $htmlItins = false;

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields['Login2']['Options'] = $this->regionOptions;
    }

    public function setLocalSetting($region)
    {
        if ($region == 'Germany') {
            $this->domain = 'de';
            $this->subDomain = 'booking';
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        $this->setLocalSetting($this->AccountFields['Login2']);

        $this->http->removeCookies();
        $this->http->GetURL("https://{$this->subDomain}.stenaline.{$this->domain}/my-pages?page=my-profile");

        if (!$this->http->ParseForm("form1")) {
            return $this->checkErrors();
        }
        $this->http->Form = [];
        $this->http->FormURL = "https://{$this->subDomain}.stenaline.{$this->domain}/services/LoginService.ashx";
        $this->http->SetInputValue('model', '{"Email":"' . $this->AccountFields['Login'] . '","ReferenceCode":null,"BookingRefEmail":null,"LoggedIn":false,"LoggedInName":null,"Password":"' . $this->AccountFields['Pass'] . '","RememberMe":true,"MenuItems":[],"MyPageMenuName":null,"LoggOutButtonText":"Log Out","LogOutReturnUrl":"https://www.stenaline.' . $this->domain . '/my-pages?page=my-profile","GetForgotPasswordEmail":false,"GetEmailResult":null,"LoggInResult":"","GetForgotPasswordAgentId":false,"AgentPartId":null,"ErrorMessage":null,"SuccessMessage":null}');
        $this->http->SetInputValue('_method', "PUT");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        $this->setLocalSetting($this->AccountFields['Login2']);

        $arg["CookieURL"] = "https://{$this->subDomain}.stenaline.{$this->domain}/my-pages?page=my-profile";
        $arg['SuccessURL'] = "https://{$this->subDomain}.stenaline.{$this->domain}/my-pages?page=my-profile";

        return $arg;
    }

    public function checkErrors()
    {
        // Service Unavailable
        // if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]"))
        //     throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Your web request has been directed to this page due to problems with our web servers. Please try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (isset($response->LoggedIn) && $response->LoggedIn == 'true') {
            // Name
            if (isset($response->LoggedInName)) {
                $this->SetProperty("Name", beautifulName($response->LoggedInName));
            }

            return true;
        }
        // Invalid credentials
        if (!empty($response->LoggInResult)) {
            throw new CheckException($response->LoggInResult, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://{$this->subDomain}.stenaline.{$this->domain}/my-pages?page=my-profile");
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode("//label[contains(text(), 'Points remaining') or contains(text(), 'Verbleibende Extra-Punkte') or contains(text(), 'Aktueller Punktestand')]", null, true, "/([\d\,\.\-]+)$/"));
        // Loyalty member
        $this->SetProperty("LoyaltyMember", $this->http->FindSingleNode("//label[contains(text(), 'Loyalty member:') or contains(text(), 'Extra-Nummer:')]/following-sibling::label[1]"));
        // Earned in current year
        $this->SetProperty("EarnedInCurrentYear", $this->http->FindSingleNode("//div[span[label[contains(text(), 'Earned in current year') or contains(text(), 'Gesammelte Punkte im aktuellen Jahr')]]]/following-sibling::div[1]/span/label[1]"));
        // Loyalty member level
        $this->SetProperty("LoyaltyType", $this->http->FindSingleNode("//label[contains(text(), 'Loyalty member level:') or contains(text(), 'Extra-Status:')]/following-sibling::label[1]"));

        // Expiration Date
        $expNodes = $this->http->XPath->query("//div[span[label[contains(text(), 'Points due to expire') or contains(text(), 'Verfallende Punkte')]]]/following-sibling::div[span[label[position() = 1 and contains(text(), ':') and not(@class)]]]");
        $this->logger->info("Total {$expNodes->length} nodes were found");
        $exp = null;

        for ($i = 0; $i < $expNodes->length; $i++) {
            $text = $this->http->FindSingleNode("span[1]", $expNodes->item($i));
            $data = explode(':', $text);

            if (count($data) != 2) {
                $this->logger->info("[Invalid node]: " . $text);

                continue;
            }
            $date = $data[0];
            $date = str_replace('/', '.', $date); // proper day.month.year format
            $this->logger->info("[{$data[0]}]: {$data[1]}");
            $expiringBalance = $data[1];

            if ((!isset($exp) || strtotime($date) < $exp) && $expiringBalance > 0) {
                $exp = strtotime($date);
                $this->SetExpirationDate($exp);
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $expiringBalance);
            }// if (!isset($exp) || strtotime($date) < $exp)
        }// for ($i = 0; $i < $expNodes->length; $i++)
    }

    public function ParseItineraries(): array
    {
        $this->logger->notice(__METHOD__);

        $result = $this->ParseItinerariesHtml();

        if (!$result && !$this->htmlItins) {
            $this->sendNotification('try json itineraries // MI');
            // $result = $this->ParseItinerariesJson();
        }

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
            "Email" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
            "Region" => [
                "Options"         => $this->regionOptions,
                "Type"            => "string",
                "Size"            => 7,
                "InputAttributes" => "style=\"width: 300px;\"",
                "Value"           => "",
                "Required"        => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        $this->logger->notice(__METHOD__);

        switch ($arFields['Region'] ?? '') {
            case "Germany":
                $this->setLocalSetting($arFields['Region']);

                break;

            case "UK":
            default:
                $this->domain = 'co.uk';
        }

        return "https://{$this->subDomain}.stenaline.{$this->domain}/login";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('Region => ' . $arFields['Region']);
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->http->GetURL("https://{$this->subDomain}.stenaline.{$this->domain}/book/amendment/AmendCancelInformation?reservationNo={$arFields['ConfNo']}&language=en&balancetopay=0&email={$arFields['Email']}");

        $response = $this->http->JsonLog(null, 3);

        if (!empty($response->ErrorMessage)) {
            return $response->ErrorMessage;
        }// реально так при неверном вводе

        $it = $this->ParseItineraryHtml($arFields['ConfNo'], $arFields['Email']);

        return null;
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'UK';
        }

        return $region;
    }

    private function ParseItineraryJson(string $booking): array
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->logger->info("Parse Itinerary #{$booking}", ['Header' => 3]);
        $this->http->GetURL("https://{$this->subDomain}.stenaline.{$this->domain}/book/amendment/AmendCancelInformation?reservationNo={$booking}&language=de&balancetopay=0");
        $data = $this->http->JsonLog(null, 3, true);

        $result['Kind'] = 'T';
        $result['TripCategory'] = TRIP_CATEGORY_CRUISE;
        // RecordLocator
        $result['RecordLocator'] = ArrayVal($data, 'ResCode');

        if (!$result['RecordLocator']) {
            return [];
        }

        // Passengers
        $passengers = [];
        $guests = ArrayVal($data, 'GuestListOut', []);

        foreach ($guests as $guest) {
            $passengers[] = beautifulName(trim(sprintf('%s %s',
               ArrayVal($guest, 'FirstName'), ArrayVal($guest, 'SurName')
            )));
        }
        $result['Passengers'] = $passengers;

        // TripSegments
        $cruise = [];
        $segment1 = [];
        $segment1['Port'] = trim(ArrayVal($data, 'RouteOut'));
        $depDate = ArrayVal($data, 'DepartureOutShort');
        $depDate = preg_replace('/\//', '.', $depDate); // d/m/y to d.m.y
        $segment1['DepDate'] = strtotime($depDate);
        $segment2 = [];
        $segment2['Port'] = trim(ArrayVal($data, 'RouteHome'));
        $arrDate = ArrayVal($data, 'DepartureHomeShort');
        $arrDate = preg_replace('/\//', '.', $arrDate);
        $segment2['ArrDate'] = strtotime($arrDate);

        if (empty($data['DepartureHomeShort']) && $segment1['DepDate'] < time() && !$this->ParsePastIts) {
            $this->logger->error("Skipping itinerary in the past");

            return [];
        }

        $cruise = [$segment1, $segment2];
        $result['TripSegments'] = $this->converter->Convert($cruise);

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function ParseItinerariesJson(): array
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $bookings = $this->http->FindNodes('//*[contains(@class, "booking-number")]/b');

        foreach ($bookings as $book) {
            $itin = $this->ParseItineraryJson($book);

            if ($itin) {
                $result[] = $itin;
            }
        }

        $noItins = $this->http->FindSingleNode('//li[contains(text(), "Keine Daten gefunden") or contains(text(), "No data found")]');

        if (!$result && $noItins) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    private function ParseItinerariesHtml(): array
    {
        $this->logger->notice(__METHOD__);

        $result = [];
        $numbers = [];

        $this->http->GetURL("https://{$this->subDomain}.stenaline.{$this->domain}/my-pages/my-bookings");

        if ($this->ParsePastIts && $this->http->ParseForm('form1')) {
            $this->http->SetInputValue('__EVENTTARGET', 'main_2$maincol1_0$mypagecontent_0$tglShowAll');
            $this->http->SetInputValue('__EVENTARGUMENT', '');
            $this->http->PostForm();
        }

        $noItins = $this->http->FindSingleNode('//li[contains(text(), "Keine Daten gefunden") or contains(text(), "No data found")]');

        if (!$result && $noItins) {
            return $this->noItinerariesArr();
        }

        $itineraries = $this->http->XPath->query("//ul[contains(@class, 'mypage-bookings')]/li[@data-rescode]");
        $this->logger->info("Total {$itineraries->length} html itineraries found");

        if ($itineraries) {
            $this->htmlItins = true;
        }

        $skippedPast = 0;

        for ($i = 0; $i < $itineraries->length; $i++) {
            $date = $this->http->FindSingleNode("(.//div[contains(@class, 'booking-time')])[1]",
                $itineraries->item($i));
            $date = str_replace('.', '/', $date);
            $reservationNo = $this->http->FindSingleNode("./@data-rescode", $itineraries->item($i));
            $this->logger->info("Reservation: {$reservationNo} at {$date}");
            $date = $this->ModifyDateFormat($date, '/', true);

            if (strtotime($date) > time() || $this->ParsePastIts) {
                $numbers[] = $reservationNo;
            } else {
                $skippedPast++;
                $this->logger->error("Skipping itinerary in the past");
            }
        }// for ($i = 0; $i < $itineraries->length; $i++)

        if (empty($numbers) && $itineraries->length > 0 && $itineraries->length === $skippedPast) {
            $this->logger->debug("all past, skipped -> noItineraries");

            return $this->noItinerariesArr();
        }

        foreach ($numbers as $number) {
            $result[] = $this->ParseItineraryHtml($number);
        }// foreach ($numbers as $number)

        return $result;
    }

    private function ParseItineraryHtml(string $number, ?string $email = null): array
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Parse Itinerary #{$number}", ['Header' => 3]);

        // $email - !empty if from retrieve
        $extUrl = isset($email) ? "&Email={$email}" : "";

        $this->http->RetryCount = 1;
        $itinUrl = "https://{$this->subDomain}.stenaline.{$this->domain}/book/Confirmation?ResCode={$number}{$extUrl}";
        $this->http->GetURL($itinUrl);

        if ($this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)) {
            sleep(2);
            $this->http->GetURL($itinUrl);
        }
        $this->http->RetryCount = 2;

        $f = $this->itinerariesMaster->add()->ferry();
        $f->general()
            ->confirmation($this->http->FindSingleNode('(//p[contains(@class, "reservation-code")])[1]'));

        if ($this->http->FindSingleNode('//div[contains(text(), "This reservation is cancelled.")]')) {
            $f->general()->cancelled();
        }

        // Currency
        $totalStr = $this->http->FindSingleNode("(//span[contains(text(), 'Total Price') or contains(text(), 'Gesamtpreis') or contains(text(), 'Original Fare')]/following-sibling::span)[1]");
        $currency = $this->currency($totalStr);
        // TotalCharge
        $total = $this->http->FindPreg("/([\d\.\,\s]+)/", false, $totalStr);

        if ($currency == 'GBP') {
            $totalCharge = PriceHelper::cost($total);
        } elseif ($currency == 'EUR') {
            $totalCharge = PriceHelper::cost($total, '.', ',');
        } else {
            $this->sendNotification('check default total');
            $totalCharge = PriceHelper::cost($total, '.', ',');
        }

        if ($total && !isset($totalCharge)) {
            $this->sendNotification('check total');
        } else {
            $f->price()
                ->total($totalCharge)
                ->currency($currency);
        }
        // TripSegments
        $nodes = $this->http->XPath->query("//h2[contains(text(), 'Booking Summary') or contains(text(), 'Buchungsübersicht')]/following-sibling::div[1]/div");
        $this->logger->info("Found {$nodes->length} segments");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $s = $f->addSegment();
            $s->extra()
                ->vessel($this->http->FindSingleNode('(//span[contains(text(), "Ship") or contains(text(), "Fähre")]/following-sibling::span[1])[1]', $node));

            // depPort
            $port = $this->http->FindSingleNode(".//h1[contains(@class, 'sub-page-header')]", $node);
            $s->departure()->name($this->http->FindPreg('/^(.+?)\s*\-/', false, $port));
            // arrPort
            $s->arrival()->name($this->http->FindPreg('/\s*\-\s*(.+?)$/', false, $port));

            if (!$s->getDepName()) {
                $this->sendNotification('check html itinerary port // MI');
            }

            // depDate
            $depDateInfo = $this->http->FindSingleNode(".//span[contains(text(), 'Departs') or contains(text(), 'Abfahrt')]/following-sibling::span[1]",
                $node);
            $depTime = $this->http->FindPreg("/(\d{2}:\d{2})$/ims", false, $depDateInfo);
            $depDate = $this->http->FindPreg("/(\d+\s+[a-z]+\s+\d{4})/ims", false, $depDateInfo);
            $depDate = $this->dateStringToEnglish($depDate);
            $depDate = "{$depDate} {$depTime}";
            $this->logger->info("DepDate: $depDate / " . strtotime($depDate));
            $s->departure()->date(strtotime($depDate));

            // rrrDate
            $arrDateInfo = $this->http->FindSingleNode(".//span[contains(text(), 'Arrives') or contains(text(), 'Ankunft')]/following-sibling::span[1]",
                $node);
            $arrTime = $this->http->FindPreg("/(\d{2}:\d{2})$/ims", false, $arrDateInfo);
            $arrDate = $this->http->FindPreg("/(\d+\s+[a-z]+\s+\d{4})/ims", false, $arrDateInfo);
            $arrDate = $this->dateStringToEnglish($arrDate);
            $arrDate = "{$arrDate} {$arrTime}";
            $this->logger->info("ArrDate: $arrDate / " . strtotime($arrDate));
            $s->arrival()->date(strtotime($arrDate));
            // TODO: need remake throw Passengers. no examples yet in germany
            $cabin = $this->http->FindSingleNode(".//text()[normalize-space()='Vehicles' or normalize-space()='Fahrzeuge']/ancestor::p[1]/following-sibling::p[1]/span[contains(translate(text(),'ABCDECONOMY','dddddddddd'),'dddd')]", $node);

            /*if (empty($cabin)) {
                $cabin = $this->http->FindSingleNode("./descendant::div[@class='details_container']/p[position()=5 or position()=6][count(.//text()[normalize-space()!=''][not(contains(.,'......'))])=1]/span[1]", $node);

                if ($this->domain == 'de') {
                    $this->sendNotification('pax text -> cabin (de) // MI');
                }
            }*/
            $s->setCabin($cabin, false, true);
            $place = $this->http->FindSingleNode(".//text()[normalize-space()='Vehicles' or normalize-space()='Fahrzeuge']/ancestor::p[1]/following-sibling::p[2]/span[1]", $node);

            if (!empty($place)) {
                $s->booked()->accommodation($place);
            }
        }// for ($i = 0; $i < $nodes->length; $i++)

        $acc = array_filter(array_unique($this->http->FindNodes("//text()[normalize-space()='Extra Membership Number']/ancestor::span[1]/following-sibling::span[1]")));

        if (!empty($acc)) {
            $f->program()->accounts($acc, false);
        }
        $spent = $this->http->FindSingleNode("(//text()[normalize-space()='Extra points used for payment']/ancestor::span[1]/following-sibling::span[1])[1]");

        if (!empty($spent)) {
            $f->price()->spentAwards($spent);
        }

        /*
        $travellers = array_filter(
            array_unique(
                $this->http->FindNodes("//h2[normalize-space()='Passenger List' or normalize-space()='Gästelisteinformation']/following::div[1]/div[position()<=2 and .//span[contains(@class,'arrow')] or position()=1]//p/descendant::span[1]")),
            function ($s) {
                return $s == 'Anonymous';
            }
        );

        if (!empty($travellers)) {
            $f->general()->travellers($travellers, true);
        }
        */

        $language = 'en';

        if (isset($this->AccountFields['Login2']) && $this->AccountFields['Login2'] == 'Germany') {
            $language = 'de';
        }
        $extUrl = isset($email) ? "&email={$email}" : "";
        $this->http->GetURL("https://{$this->subDomain}.stenaline.{$this->domain}/book/amendment/AmendCancelInformation?reservationNo={$number}&language={$language}&balancetopay=0{$extUrl}");
        $response = $this->http->JsonLog(null, 3);

        $travellers = [];

        foreach ($f->getSegments() as $i => $s) {
            $vehicles = [];
            $guests = [];

            if ($i === 0) {
                if (isset($response->LeadVehiclesOut) && is_array($response->LeadVehiclesOut)) {
                    $vehicles = $response->LeadVehiclesOut;
                }

                if (isset($response->TrailersOut) && is_array($response->TrailersOut)) {
                    $vehicles = array_merge($vehicles, $response->TrailersOut);
                }

                if (isset($response->GuestListOut) && is_array($response->GuestListOut)) {
                    $guests = $response->GuestListOut;
                }
            }

            if ($i === 1) {
                if (isset($response->LeadVehiclesHome) && is_array($response->LeadVehiclesHome)) {
                    $vehicles = $response->LeadVehiclesHome;
                }

                if (isset($response->TrailersHome) && is_array($response->TrailersHome)) {
                    $vehicles = array_merge($vehicles, $response->TrailersHome);
                }

                if (isset($response->GuestListHome) && is_array($response->GuestListHome)) {
                    $guests = $response->GuestListHome;
                }
            }

            foreach ($vehicles as $veh) {
                // Car max 6m long > 2m high - x11mha
                // Car max 4.2m long, 2m high
                // Minivan max. 3m H/6m L - OHMR812
                // Caravan/Trl 4m long, 4m high
                if (preg_match("/(Car) max ([\d\.]+m) long,? ([> ]*[\d\.]+m) high/iu", $veh->Value, $m)) {
                    $v = $s->addVehicle();
                    $v->setType($m[1] . ' ' . $veh->Key)
                        ->setLength($m[2])
                        ->setHeight($m[3]);
                } elseif (preg_match("/(Pkw) max ([\d\.\,]+m)L\/([\d\.\,]+m)H inkl. Dachl./iu", $veh->Value, $m)) {
                    $v = $s->addVehicle();
                    $v->setType($m[1] . ' ' . $veh->Key)
                        ->setLength($m[2])
                        ->setHeight($m[3]);
                } elseif (preg_match("/(Motorhome\/Minibus) to ([\d\.]+m) long/iu", $veh->Value, $m)) {
                    $v = $s->addVehicle();
                    $v->setType($m[1] . ' ' . $veh->Key)
                        ->setLength($m[2]);
                } elseif (preg_match("/(Minivan) max. ([\d\.]+m) H\s*\/\s*([\d\.]+m) L/iu", $veh->Value, $m)) {
                    $v = $s->addVehicle();
                    $v->setType($m[1] . ' ' . $veh->Key)
                        ->setLength($m[3])
                        ->setHeight($m[2]);
                } elseif (preg_match("/^(.+?) (?:max.? )?([\d\.]+m) L\s*\/\s*([\d\.]+m) H/iu", $veh->Value, $m)) {
                    $v = $s->addVehicle();
                    $v->setType($m[1] . ' ' . $veh->Key)
                        ->setLength($m[2])
                        ->setHeight($m[3]);
                } elseif (preg_match("/^(.+?) ([\d\.]+m) long, ([\d\.]+m) high/iu", $veh->Value, $m)) {
                    $v = $s->addVehicle();
                    $v->setType($m[1] . ' ' . $veh->Key)
                        ->setLength($m[2])
                        ->setHeight($m[3]);
                } else {
                    $v = $s->addVehicle();
                    $v->setModel($veh->Value . ' - ' . $veh->Key);
                    $this->logger->notice($veh->Value . ' - ' . $veh->Key);
                    $sendNotification = true;
                }
            }
            $adult = $child = 0;

            foreach ($guests as $passenger) {
                if ($passenger->FirstName != 'Anonymous') {
                    $travellers[] = beautifulName($passenger->FirstName . ' ' . $passenger->SurName);
                }

                if (in_array(trim($passenger->CustomerCategoryDescription), ['Adult', 'Rentner/Senior', 'Erwachsener'])) {
                    $adult++;
                }

                if (in_array(trim($passenger->CustomerCategoryDescription), ['Child', 'Kleinkind', 'Infant'])) {
                    $child++;
                }
            }

            if ($adult > 0) {
                $s->booked()->adults($adult);
            }

            if ($child > 0) {
                $s->booked()->kids($child);
            }
        }

        if (isset($sendNotification)) {
            $this->sendNotification("check vehicle values: need to fix regexp");
        }

        if (!empty($travellers)) {
            $travellers = array_unique($travellers);
            $f->general()->travellers($travellers, true);
        } else {
            $f->general()->traveller($response->Name, true);
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($result = $f->toArray(), true), ['pre' => true]);

        return $result;
    }

    private function dateStringToEnglish($dateStr, $lang = 'de')
    {
        if ($month = $this->http->FindPreg('/([a-z]+)/i', false, $dateStr)) {
            if ($monthEn = MonthTranslate::translate($month, $lang)) {
                return preg_replace("#{$month}#i", $monthEn, $dateStr);
            }
        }

        return $dateStr;
    }
}
