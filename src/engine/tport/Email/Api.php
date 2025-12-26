<?php

namespace AwardWallet\Engine\tport\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Common\Shortcut\DetailedAddress;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Api extends \TAccountChecker
{
    public $mailFiles = "tport/it-12037075.eml, tport/it-12087385.eml, tport/it-12737719.eml, tport/it-30273609.eml, tport/it-30273614.eml";
    private $reFrom = "@travelport.com";
    private $reSubject = [
        "en" => "View Your Itinerary:",
        "it" => "Visualizzare il proprio itinerario:",
        "pt" => "Exibir o seu itinerário:",
    ];
    private $reBody = 'viewtrip.travelport.com';
    private $reBody2 = [
        "en" => ["To see the details of your trip", "Your e-ticket is the most up to date record of your itinerary"],
        "it" => ["Per visualizzare i dettagli del vostro viaggio", "Numero di prenotazione"],
        "pt" => ["Para ver os detalhes da sua viagem"],
    ];

    private $lang = "en";
    private $type = 'UnknownType';
    private $provider = null;
    private $knownProviders = [
        'WEBJET'                => 'webjet',
        'ZUJI'                  => 'zuji',
        'ZUJI TRAVEL PTE LTD'   => 'zuji',
        'Omega Travel Group'    => 'omega',
        'HOGG ROBINSON AUSTRIA' => 'hoggrob',
        'ATPI SOUTH AFRICA'     => 'atpi',
        'Europcar'              => 'europcar',
        //'MTA TRAVEL' => 'mta',//MTA TRAVEL JASON DENISEN //!!!don't add mta until it ignoreTraxo
        'Avis Rent A Car System' => 'avis',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //go to parse by It6098511.php
        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";

        if ($this->http->XPath->query("//text()[{$ruleTime}]/ancestor::table[2][count(descendant::text()[{$ruleTime}])=2]")->length > 0) {
            $this->logger->debug('go to parse by It6098511');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . 'ViewYourItinerary' . ucfirst($this->lang));

        $this->parseLink($email);

        return $email;
    }

    public function parseLink(Email $email)
    {
        if ($url = $this->http->FindSingleNode("(//a[contains(@href, 'https://viewtrip.travelport.com/itinerary') or contains(@href, 'https://viewtrip.travelport.com/#!/itinerary')]/@href)[1]")) {
            $parts = explode("?", $url);

            if (isset($parts[1])) {
                parse_str($parts[1], $params);

                if (isset($params['loc'], $params['lName'], $params['pcc'])) {
                    return $this->parseApi($email, $params['loc'], $params['lName'],
                        $params['pc'] ?? null);
                }
            }
        } elseif (($url = $this->http->FindSingleNode("(//a[contains(@href, 'https://services.webjet.com.au/web/itinerary/viewprovidertrip/')]/@href)[1]"))
            && ($pnr = $this->http->FindPreg("/\/([A-Z\d]{6})\?(?:FirstName|Source)=/", false, $url))
        ) {
            $parts = explode("?", $url);

            if (isset($parts[1])) {
                parse_str($parts[1], $params);

                if (isset($params['FirstName'], $params['LastName'], $params['Email'])) {
                    return $this->parseApi($email, $pnr, $params['LastName'], null);
                }
            }
        }

        return $email;
    }

    public function parseApi(Email $email, $loc, $lastname, $pc)
    {
        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $this->http->GetUrl("https://viewtripnextgen-api.travelport.com/api/v1/itinerary/{$loc}?lName={$lastname}&providerCode={$pc}&cultureInfo=en-GB&pf=");

        if ($this->http->Response['code'] != 200) {
            $this->logger->info("response code is: " . $this->http->Response['code']);

            return;
        }

        $json = json_decode($this->http->Response['body']);

        if (!isset($json->Locator)) {
            $this->logger->info("Locator not found");

            return;
        }

        if (!empty($json->EmailAddress) && preg_match("#^[^@]+@[^@]+$#", $json->EmailAddress)) { //CHRISTOFFER@U@KJAER@HOTMAIL.COM
            $email->setUserEmail($json->EmailAddress);
        }

        $ta = $email->ota();
        $ta->confirmation($json->Locator);

        foreach ($json->Agency->PhoneList as $value) {
            if (preg_match("#^([\d+\-\(\) \.]{7,})\b(?:[ -]+(\w.+))?$#", $value, $m)) {
                if (isset($m[2])) {
                    if (!in_array(trim($m[1]), array_column($email->obtainTravelAgency()->getProviderPhones(), 0))) {
                        $email->ota()->phone(trim($m[1]), trim($m[2]));
                    }
                } else {
                    if (!in_array(trim($value), array_column($email->obtainTravelAgency()->getProviderPhones(), 0))) {
                        $email->ota()->phone(trim($value));
                    }
                }
            }
        }

        if (!empty($json->Agency->Name[0])) {
            $finded = false;

            foreach ($this->knownProviders as $name => $prov) {
                if ($json->Agency->Name[0] == $name || strpos($json->Agency->Name[0], $name) === 0) {
                    $ta->code($prov);
                    $finded = true;

                    break;
                }
            }

            if ($finded == false) {
                $this->logger->info('Unknown provider');
            }
        }

        $airs = [];
        $events = [];
        $trains = [];
        $rentals = [];
        $hotels = [];

        foreach ($json->Segments as $Segment) {
            switch ($Segment->SegmentType) {
                case 'Tvl_MIS':
                case 'Tvl_AIR':
                    break;

                    break;

                case 'Tour':
                    $events[] = $Segment;

                    break;

                case 'Car':
                    $rentals[] = $Segment;

                    break;

                case 'Hotel':
                    $hotels[] = $Segment;

                    break;

                case 'Train':
                    $trains[] = $Segment;

                    break;

                case 'Flight':
                    if ($Segment->AirlineName == 'Tour' && $Segment->FlightNumber = '100' && $Segment->DepartCode == $Segment->ArriveCode) {
                        break;
                    }

                    if (isset($Segment->Guests[0]) && !empty($Segment->Guests[0]->EticketNumber)) {
                        $ticket = substr($Segment->Guests[0]->EticketNumber, 0, 3);
                        $airs[$ticket][] = $Segment;
                    } else {
                        $airs['empty'][] = $Segment;
                    }

                    break;

                default:
                    $this->logger->info('Unknown segment type: ' . $Segment->SegmentType);

                    return;
            }
        }

        //##################
        //##   FLIGHTS   ###
        //##################

        foreach ($airs as $Segments) {
            $f = $email->add()->flight();

            $f->general()->confirmation($json->Locator);

            $passengers = [];
            $tickets = [];
            $accounts = [];

            foreach ($Segments as $serment) {
                foreach ($serment->Guests as $Guest) {
                    $passengers[] = $Guest->FirstName . ' ' . $Guest->LastName;
                    $tickets[] = $Guest->EticketNumber;
                    $accounts[] = $Guest->LoyaltyInfo;
                }
            }

            if (!empty(array_filter($passengers))) {
                $f->general()->travellers(array_unique(array_filter($passengers)), true);
            }

            if (!empty(array_filter($tickets))) {
                $f->issued()->tickets(array_unique(array_filter($tickets)), false);
            }

            if (!empty(array_filter($accounts))) {
                $f->program()->accounts(array_unique(array_filter($accounts)), false);
            }

            foreach ($Segments as $Segment) {
                $s = $f->addSegment();

                if ($rl = $this->re("#^([A-Z\d]{5,6})(?: [A-Z\d]{5,6}|$)#", $Segment->Confirmation)) {
                    $s->airline()->confirmation($rl);
                }

                // airline
                $s->airline()
                    ->name($Segment->AirlineName)
                    ->number($Segment->FlightNumber)
                    ->operator($Segment->OperatingCarrier, true, true);

                // Arrival
                $s->departure()
                    ->code($Segment->DepartCode)
                    ->name($Segment->DepartName)
                    ->date(strtotime($Segment->SegmentDates[0]));

                $s->arrival()
                    ->code($Segment->ArriveCode)
                    ->name($Segment->ArriveName)
                    ->date(strtotime($Segment->SegmentDates[1]));

                // Extra
                $s->extra()
                    ->aircraft($Segment->EquipmentInfo->Description, true, true)
                    ->cabin($Segment->ClassOfService->Description, true, true)
                    ->bookingCode($Segment->ClassOfService->Code, true, true)
                    ->duration($Segment->TravelTime, true, true)
                    ->stops($Segment->Stops, true, true);

                if (!empty($Segment->Seats)) {
                    $s->extra()->seats($Segment->Seats);
                }
            }
        }

        //#################
        //##   TRAINS   ###
        //#################

        foreach ($trains as $Segment) {
            $f = $email->add()->train();

            if (!empty($Segment->Confirmation) && ($rl = $this->re("#^([A-Z\d]{5,6})(?: [A-Z\d]{5,6}|$)#",
                    $Segment->Confirmation))
            ) {
                $f->general()->confirmation($rl);
            } else {
                $f->general()->noConfirmation();
            }

            $passengers = [];
            $tickets = [];
            $accounts = [];

            foreach ($Segment->Guests as $Guest) {
                $passengers[] = $Guest->FirstName . ' ' . $Guest->LastName;
                $tickets[] = $Guest->EticketNumber;
                $accounts[] = $Guest->LoyaltyInfo;
            }

            if (!empty(array_filter($passengers))) {
                $f->general()->travellers(array_unique(array_filter($passengers)), true);
            }

            if (!empty(array_filter($tickets))) {
                $f->setTicketNumbers(array_unique(array_filter($tickets)), false);
            }

            if (!empty(array_filter($accounts))) {
                $f->program()->accounts(array_unique(array_filter($accounts)), false);
            }

            $s = $f->addSegment();

            // train info
            $s->extra()
                ->number($this->re("#(\d+)#", $Segment->Title))
                ->service($Segment->ProviderName);

            // Arrival
            $s->departure()
                ->code($Segment->DepartCode)
                ->name($Segment->DepartName)
                ->date(strtotime($Segment->SegmentDates[0]));

            $s->arrival()
                ->code($Segment->ArriveCode)
                ->name($Segment->ArriveName)
                ->date(strtotime($Segment->SegmentDates[1]));

            // Extra
            $s->extra()
                ->model($Segment->EquipmentInfo->Description, true, true)
                ->cabin($Segment->ClassOfService->Description, true, true)
                ->bookingCode($Segment->ClassOfService->Code, true, true)
                ->duration($Segment->TravelTime, true, true);

            if (!empty($Segment->Seats)) {
                $s->extra()->seats($Segment->Seats);
            }
        }

        //#################
        //##   HOTELS   ###
        //#################

        foreach ($hotels as $Segment) {
            $f = $email->add()->hotel();

            if (!empty($Segment->SegmentInfo->Confirmation) && ($rl = $this->re("#^([A-Z\d]{5,6})(?: [A-Z\d]{5,6}|$)#",
                    $Segment->SegmentInfo->Confirmation))
            ) {
                $f->general()->confirmation($rl);
            } else {
                $f->general()->noConfirmation();
            }

            $passengers = [];
            $accounts = [];

            foreach ($Segment->SegmentInfo->Guests as $Guest) {
                $passengers[] = $Guest->FirstName . ' ' . $Guest->LastName;
                $accounts[] = $Guest->LoyaltyInfo;
            }

            if (!empty(array_filter($passengers))) {
                $f->general()->travellers(array_unique(array_filter($passengers)), true);
            }

            if (!empty(array_filter($accounts))) {
                $f->program()->accounts(array_unique(array_filter($accounts)), false);
            }

            $address = array_filter([
                $Segment->SegmentInfo->PropertyInfo->Address,
                $Segment->SegmentInfo->PropertyInfo->City,
                $Segment->SegmentInfo->PropertyInfo->State,
                $Segment->SegmentInfo->PropertyInfo->Zip,
                $Segment->SegmentInfo->PropertyInfo->Country,
            ]);

            if (empty($address) && is_array($Segment->SegmentInfo->PropertyInfo->Name)) {
                $address = array_filter($Segment->SegmentInfo->PropertyInfo->Name);
            }

            if (empty($address)) {
                $address[] = $Segment->DepartName;
            }
            $f->hotel()
                ->name($Segment->SegmentInfo->ProviderName)
                ->chain($Segment->SegmentInfo->ChainName, true, true)
                ->address(implode(", ", $address))
                ->phone($Segment->SegmentInfo->ContactInfo->Phone, true, true)
                ->fax($Segment->SegmentInfo->ContactInfo->Fax, true, true);
            $da = $f->hotel()->detailed();
            $jsonObj = $Segment->SegmentInfo->PropertyInfo;
            $this->fillDetailAddress($da, $jsonObj);

            $f->booked()
                ->checkIn(strtotime($Segment->SegmentInfo->CheckIn))
                ->checkOut(strtotime($Segment->SegmentInfo->CheckOut))
                ->guests($Segment->SegmentInfo->RoomInfo->Guests)
                ->rooms($Segment->SegmentInfo->RoomInfo->Rooms);

            $f->price()
                ->total($Segment->SegmentInfo->RoomInfo->ApproxTotal, true)
                ->currency($Segment->SegmentInfo->RoomInfo->Currency, true);

            $r = $f->addRoom();
            $r->setRate($Segment->SegmentInfo->RoomInfo->Rate, true)
                ->setDescription(implode(",", (array) $Segment->SegmentInfo->RoomInfo->RoomTypeDescription), true);
        }

        //###############
        //##   CARS   ###
        //###############

        foreach ($rentals as $Segment) {
            $f = $email->add()->rental();

            if (!empty($Segment->SegmentInfo->Confirmation) && ($rl = $this->re("#^([A-Z\d]{5,6})(?: [A-Z\d]{5,6}|$)#",
                    $Segment->SegmentInfo->Confirmation))
            ) {
                $f->general()->confirmation($rl);
            } else {
                $f->general()->noConfirmation();
            }

            $passengers = [];
            $accounts = [];

            foreach ($Segment->SegmentInfo->Guests as $Guest) {
                $passengers[] = $Guest->FirstName . ' ' . $Guest->LastName;
                $accounts[] = $Guest->LoyaltyInfo;
            }

            if (!empty(array_filter($passengers))) {
                $f->general()->travellers(array_unique(array_filter($passengers)), true);
            }

            if (!empty(array_filter($accounts))) {
                $f->program()->accounts(array_unique(array_filter($accounts)), false);
            }

            $keyword = $Segment->SegmentInfo->ProviderName;
            $finded = false;

            foreach ($this->knownProviders as $name => $prov) {
                if ($keyword == $name || strpos($keyword, $name) === 0) {
                    $f->program()->code($prov);
                    $finded = true;

                    break;
                }
            }

            if ($finded == false) {
                $f->extra()->company($keyword);
//                $f->program()->keyword($keyword);
                $this->logger->info('Unknown provider rental');
            }

            $addressPickUp = array_filter([
                $Segment->SegmentInfo->PickupInfo->Address,
                $Segment->SegmentInfo->PickupInfo->City,
                $Segment->SegmentInfo->PickupInfo->State,
                $Segment->SegmentInfo->PickupInfo->Zip,
                $Segment->SegmentInfo->PickupInfo->Country,
            ]);

            $location = trim(implode(',', (array) $Segment->SegmentInfo->PickupInfo->Name) . '; ' . implode(", ",
                    $addressPickUp), ' ;');
            $f->pickup()
                ->date(strtotime($Segment->SegmentInfo->Pickup))
                ->location($location)
                ->phone($Segment->SegmentInfo->PickupInfo->ContactInfo->Phone, false, true)
                ->fax($Segment->SegmentInfo->PickupInfo->ContactInfo->Fax, false, true)
                ->openingHours($Segment->SegmentInfo->PickupInfo->BusinessHrs, true, true);

            $da = $f->pickup()->detailed();
            $jsonObj = $Segment->SegmentInfo->PickupInfo;
            $this->fillDetailAddress($da, $jsonObj);

            $addressDropOff = array_filter([
                $Segment->SegmentInfo->DropoffInfo->Address,
                $Segment->SegmentInfo->DropoffInfo->City,
                $Segment->SegmentInfo->DropoffInfo->State,
                $Segment->SegmentInfo->DropoffInfo->Zip,
                $Segment->SegmentInfo->DropoffInfo->Country,
            ]);

            $location = trim(implode(',', (array) $Segment->SegmentInfo->DropoffInfo->Name) . '; ' . implode(", ",
                    $addressDropOff), ' ;');

            if (empty($location)) {
                $f->dropoff()
                    ->noLocation();
            } else {
                $f->dropoff()
                    ->location($location)
                    ->phone($Segment->SegmentInfo->DropoffInfo->ContactInfo->Phone, false, true)
                    ->fax($Segment->SegmentInfo->DropoffInfo->ContactInfo->Fax, false, true)
                    ->openingHours($Segment->SegmentInfo->DropoffInfo->BusinessHrs, true, true);
                $da = $f->dropoff()->detailed();
                $jsonObj = $Segment->SegmentInfo->DropoffInfo;
                $this->fillDetailAddress($da, $jsonObj);
            }

            $f->dropoff()
                ->date(strtotime($Segment->SegmentInfo->DropOff));

            $f->price()
                ->total($Segment->SegmentInfo->CarInfo->ApproxTotal)
                ->currency($Segment->SegmentInfo->CarInfo->Currency);

            $f->car()
                ->type($Segment->SegmentInfo->CarInfo->Description);
        }

        //#################
        //##   EVENTS   ###
        //#################

        foreach ($events as $Segment) {
            $ev = $email->add()->event();

            // General
            if (isset($Segment->Confirmation)) {
                $ev->general()->confirmation($Segment->Confirmation);
            } else {
                $ev->general()->noConfirmation();
            }
            $ev->general()->traveller($Segments[0]->Guests[0]->FirstName . ' ' . $Segments[0]->Guests[0]->LastName);

            // Place
            $ev->place()
                ->name($Segment->SegmentInfo->TourTypeName)
                ->type(Event::TYPE_EVENT);

            if ($Segment->SegmentInfo->PickUpAddress) {
                $ev->place()->address($Segment->SegmentInfo->PickUpAddress);
            } else {
                $ev->place()->address($Segment->SegmentInfo->OriginCityName . ' (' . $Segment->SegmentInfo->OriginCityCode . ')');
            }

            // Booked
            $ev->booked()
                ->start(strtotime($Segment->SegmentInfo->StartDate))
                ->guests($Segment->SegmentInfo->NumberOfPersons);

            if ($Segment->SegmentInfo->EndDate) {
                $ev->booked()->end(strtotime($Segment->SegmentInfo->EndDate));
            } else {
                $ev->booked()->noEnd();
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailProviders()
    {
        return ['tport', 'westjet'];
    }

    private function fillDetailAddress(DetailedAddress $da, $jsonObj)
    {
        if (empty($jsonObj->City) || empty($jsonObj->Address)) {
            return false;
        }
        $da
            ->city($jsonObj->City)
            ->address($jsonObj->Address);

        if (preg_match("#^\d+$#", $jsonObj->State)) {
            if (!empty($jsonObj->State)) {
                $da->zip($jsonObj->State);
            }

            if (!empty($jsonObj->Zip)) {
                $da->country($jsonObj->Zip);
            }
        } else {
            if (!empty($jsonObj->State)) {
                $da->state($jsonObj->State);
            }

            if (!empty($jsonObj->Zip)) {
                $da->zip($jsonObj->Zip);
            }

            if (!empty($jsonObj->Country)) {
                $da->country($jsonObj->Country);
            }
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }
}
