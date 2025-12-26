<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripToPDF extends \TAccountChecker
{
    public $mailFiles = "mta/it-209695623.eml, mta/it-211271026.eml, mta/it-265912614.eml, mta/it-623919405.eml";

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $dateStart;
    public $text;

    // ['date' => $date, 'name' => $name, 'type' => {'in' or 'out'}, 'placeType' => 'hotel', 'placeInfo' => {hotel address or airport code}]
    public $travelPoints;

    public static $dictionary = [
        "en" => [
            'Booking' => ['Booking', 'Quote', 'Invoice'],
            'cabinVariants' => ['ECONOMY'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'MTA') !== false) {
                if (strpos($text, 'YOUR TRAVEL ITINERARY') !== false && strpos($text, 'Accommodation') !== false && strpos($text, 'Duration') !== false) {
                    return true;
                }
            }

            if (strpos($text, 'Travelling passengers') !== false && strpos($text, 'Trip Summary') !== false) {
                return true;
            }

            if (strpos($text, 'Flight Centre') !== false || strpos($text, 'The Private Travel Company') !== false) {
                if ((strpos($text, 'Departing Flight') !== false && strpos($text, 'Travel Insurance has not been requested as part of this trip') !== false)
                    || (strpos($text, ' Accommodation') !== false && strpos($text, 'It is our pleasure to provide the following itinerary detailing the travel arrangements made on your behalf') !== false)
                ) {
                    return true;
                }
            }

            if (strpos($text, 'Departing') !== false && strpos($text, 'Thank you for booking with GOGO Vacations in conjunction with your travel advisor') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mtatravel\.com\.au$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->text .= $text;

            if (strpos($text, 'Flight Centre') !== false) {
                $email->setProviderCode('flightcentre');
            }

            $text = preg_replace("/(\n {20,}Issued On *:.*)?\n+ +Page \d+ of \d+ *\n+ *\w+\n{1,3}.*{$this->opt($this->t('Booking'))} *\d{5,}.*\n+( {0,10}\W\n)?/u", "\n\n", $text);
            $text = preg_replace("/\n {20,}Issued on *:.*\n+ *[\w ]+\n.{20,}  {$this->opt($this->t('Booking'))} *\d{5,}.*\n+( {0,10}\W\n)?/u", "\n\n", $text);

            $otaConf = $this->re("/\s+{$this->opt($this->t('Booking'))}\s*(\d{5,})\s*\n/", $text);

            if (!empty($otaConf)) {
                $email->ota()
                    ->confirmation($otaConf);
            }

            $this->dateStart = strtotime($this->re("/\n *Trip to.*\n+\s*([^\|\n\-]+)/", $text));

            if (empty($this->dateStart)) {
                $this->dateStart = strtotime($this->re("/Start Date\n+\s*(.+\d{4})\n/", $text));
            }

            if (empty($this->dateStart)) {
                $this->dateStart = strtotime($this->re("/\n *Departing *: *(.+\d{4})\n/", $text));
            }

            $segments = $this->split("/\n[ ]{0,10}(Flights|Transfer|Accommodation|Car Hire|Service Fee|Total (?:Package )?Price {10,}.*|Insurance|Activity)\n/", $text);

            //$this->logger->error(var_export($segments, true));

            $transfersText = '';
            $isContainsFlights = false;

            foreach ($segments as $sText) {
                $type = $this->re("/^[ ]*(\S.+?)(?:\n|[ ]{5})/", $sText);

                if (stripos($type, 'Flight') !== false) {
                    $this->parseFlight($email, $sText);
                    $isContainsFlights = true;
                }

                if (stripos($type, 'Transfer') !== false) {
                    $transfersText = $sText;
                }

                if (stripos($type, 'Accommodation') !== false) {
                    $this->parseHotel($email, $sText);
                }

                if (stripos($type, 'Car Hire') !== false) {
                    $this->parseCar($email, $sText);
                }

                if (stripos($type, 'Service Fee') !== false) {
                    continue;
                }

                if (stripos($type, 'Activity') !== false) {
                    continue;
                }

                if (stripos($type, 'Total Price') !== false || stripos($type, 'Total Package Price') !== false) {
                    if (preg_match("/^\s*Total (?:Package )?Price +(?<currency>[A-Z]{3})\s*(?<total>\d[\d\.\, ]*)\s*(?:\n|$)/u", $sText, $m)) {
                        $email->price()
                            ->total(PriceHelper::parse($m['total'], $m['currency']))
                            ->currency($m['currency']);
                    }
                }

                if (stripos($type, 'Insurance') !== false) {
                    break;
                }
            }

            if (!empty($transfersText) && $isContainsFlights === true) {
                // after flights and hotels
                $this->parseTransfer($email, $transfersText);
            }
        }

        $travellers = [];

        //it-209695623
        if (stripos($this->text, 'Itinerary for') !== false) {
            $travellersText = $this->re("/(Itinerary for.+)Departing\s*\:/s", $this->text);

            if (preg_match_all("/\:\s*([[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]])(?:\n|$)/", $travellersText, $m)) {
                $travellers = $m[1];
            }
        }

        //it-211271026.eml
        if (stripos($this->text, 'Travelling passengers') !== false) {
            $travellersText = $this->re("/Travelling passengers\n+(.+)\n+Trip Summary/s", $this->text);
            $travellersText = trim(preg_replace("/^\s*\W\s*$/m", "\n", $travellersText));

            $travellers = preg_split("/(\s*\n\s*|\s{3,})/", $travellersText);

            $guestType = $this->re("/\n *Trip to.*\n+.*\|\s*([^\|\n]+)\n/", $this->text);
            $adult = 0;
            $kids = 0;

            if (preg_match("/(\d+) *adult/", $guestType, $m)) {
                $adult += $m[1];
            }

            if (preg_match("/(\d+) *child/", $guestType, $m)) {
                $kids += $m[1];
            }

            if (preg_match("/(\d+) *teen/", $guestType, $m)) {
                $kids += $m[1];
            }
        }

        $travellers = preg_replace('/^\s*(?:Mrs|Mr|Miss|Mstr|Ms)\s+/', '', $travellers);
        $travellers = preg_replace('/\(.*\)\s*$/', '', $travellers);
        $travellers = array_filter(preg_replace('/^(\s*TBA)+/i', '', $travellers));

        foreach ($email->getItineraries() as $it) {
            $it->general()
                ->travellers($travellers, true);

            if (!empty($adult)) {
                switch ($it->getType()) {
                    case 'hotel':
                        /** @var \AwardWallet\Schema\Parser\Common\Hotel $it */
                        $it->booked()
                            ->guests($adult);

                        break;

                    case 'transfer':
                        /** @var \AwardWallet\Schema\Parser\Common\Transfer $it */
                        foreach ($it->getSegments() as $s) {
                            $s->extra()
                                ->adults($adult);
                        }

                        break;
                }
            }

            if (!empty($kids)) {
                switch ($it->getType()) {
                    case 'hotel':
                        /** @var \AwardWallet\Schema\Parser\Common\Hotel $it */
                        $it->booked()
                            ->kids($kids);

                        break;

                    case 'transfer':
                        /** @var \AwardWallet\Schema\Parser\Common\Transfer $it */
                        foreach ($it->getSegments() as $s) {
                            $s->extra()
                                ->kids($kids);
                        }

                        break;
                }
            }
        }

        if (strpos($parser->getCleanFrom(), 'flightcentre') !== false) {
            $email->setProviderCode('flightcentre');
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailProviders()
    {
        return ['mta', 'flightcentre'];
    }

    public function parseFlight(Email $email, $sText): void
    {
        $this->logger->debug(__FUNCTION__);

        $sText = preg_replace("/\n+ *Issued on[ \:]+.+\n\s+Itinerary Document\n+TRAVEL PLANNERS INTERNATIONAL.+\n+[}]\n+/u", "\n", $sText);
        $f = $email->add()->flight();

        if (preg_match_all("/\n *(?<traveller1>.+?) +TKT\s*(?<ticket>\d{13}) +.+(?:\n *(?<traveller2>(\S ?)+)(?=\n))?/", $sText, $m)) {
            foreach ($m[0] as $i => $v) {
                $m['traveller1'][$i] = preg_replace('/^\s*(?:Mrs|Mr|Miss|Mstr|Ms)\s+/', '', $m['traveller1'][$i]);
                $f->issued()
                    ->ticket($m['ticket'][$i], false, implode(" ", array_filter(array_map('trim', [$m['traveller1'][$i], $m['traveller2'][$i] ?? '']))));
            }
        }

        $pnrReference = $this->re("/PNR Reference[\:\s]+([A-Z\d]{6})/", $sText);

        if (!empty($pnrReference)) {
            $f->general()
                ->confirmation($pnrReference);
        } else {
            $f->general()
                ->noConfirmation();
        }

        $routes = $this->split("/(?:^|\n)( {0,10}[A-Z]{3} {1,10}(?:Departing|Return) Flight\n)/", $sText);

        foreach ($routes as $route) {
            $flights = $this->split("/\n( +[A-Z]{3} +\d{1,2}:\d{2} {5,}\d{1,2}:\d{2}(?: *\([-+] ?\d+\))?\n)/", $route);

            foreach ($flights as $ftext) {
                $s = $f->addSegment();

                $airlineNameRe = '';

                if (count($flights) === 1) {
                    $s->extra()->duration($this->re("/^[ ]*Total Duration(?:[ :]+|[ :]*\n+[ ]*)((?: ?\d{1,4} ?[hm])+)(?:[ ]{2}|$)/im", $ftext));
                    $airlineNameRe = '(?: *[A-Z\d\W ]+\n+)?';
                }

                $table1Text = $this->re("/^([\s\S]+?)\n+({$airlineNameRe}.+ (?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\n|.+\s+operated by)/", $ftext);
                $table1Text = preg_replace("/\n+[ ]*Total Duration.*$/s", '', $table1Text);
                $table1Text = preg_replace("/ {3,}\.\n/", "\n", $table1Text);
                $table1Text = preg_replace("/^( {0,10}[A-Z]{3}) {1,10}(?:Departing|Return) Flight\n/", "$1\n", $table1Text);
                $table1 = $this->createTable($table1Text, $this->rowColumnPositions($this->inOneRow($table1Text)));

                $date = null;

                if (count($table1) === 3) {
                    if (!empty($this->dateStart)) {
                        $date = $this->normalizeDateRelative(trim($table1[0]), $this->dateStart);
                    }

                    $reAirport = "/^\s*(?<time>\d{1,2}:\d{2})\s*(?:\(\s*(?<overnight>[-+] ?\d+)\s*\))?\s+(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*(?<terminal>.*)?$/s";

                    if (preg_match($reAirport, $table1[1], $m)) {
                        if (!empty($date)) {
                            $s->departure()
                                ->date(strtotime($m['time'], $date));
                        }

                        $s->departure()
                            ->code($m['code'])
                            ->name(preg_replace("/\s+/", ' ', trim($m['name'])));
                        $this->travelPoints[] = ['date' => $s->getDepDate(), 'name' => $s->getDepName(), 'type' => 'in', 'placeType' => 'flight', 'placeInfo' => $s->getDepCode()];

                        if (!empty($m['terminal'])) {
                            $s->departure()
                                ->terminal(trim(preg_replace(["/\s*\bterminal\b\s*/i", '/\s+/'], ' ', $m['terminal'])));
                        }
                    }

                    if (preg_match($reAirport, $table1[2], $m)) {
                        if (!empty($date)) {
                            $s->arrival()
                                ->date(strtotime($m['time'], $date));

                            if (!empty($m['overnight']) && !empty($s->getArrDate())) {
                                $s->arrival()
                                    ->date(strtotime($m['overnight'] . ' days', $s->getArrDate()));
                            }
                        }

                        $s->arrival()
                            ->code($m['code'])
                            ->name(preg_replace("/\s+/", ' ', trim($m['name'])));

                        $this->travelPoints[] = ['date' => $s->getArrDate(), 'name' => $s->getArrName(), 'type' => 'out', 'placeType' => 'flight', 'placeInfo' => $s->getArrCode()];

                        if (!empty($m['terminal'])) {
                            $s->arrival()
                                ->terminal(trim(preg_replace(["/\s*\bterminal\b\s*/i", '/\s+/'], ' ', $m['terminal'])));
                        }
                    }
                }

                if (count($flights) > 1) {
                    $table2Text = $this->re("/\n(.+ (?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\n(?:.+\n)*?)\n/", $ftext);
                    $table2 = $this->createTable($table2Text, $this->rowColumnPositions($this->inOneRow($table2Text)));

                    if (count($table2) < 4 && count($flights) > 1 && preg_match("/^[A-Z\d\W]+ (ECONOMY|BUSINESS)\n/", $table2[0], $m)) {
                        $table2[3] = $table2[2] ?? '';
                        $table2[2] = $table2[1] ?? '';
                        $table2[1] = $m[1];
                        $table2[1] = preg_replace("/^([A-Z\d\W]+) (ECONOMY|BUSINESS)\n/", '$1', $table2[0]);
                        $table2 = array_filter($table2);
                    }

                    if (count($table2) === 3 && count($flights) > 1 && preg_match("/^(.*\w.+?) ((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5})(\n.*|$)/", $table2[2], $m)) {
                        $table2[2] = $m[1] . $m[3];
                        $table2[3] = $m[2];
                    }

                    if (count($table2) === 4) {
                        if (preg_match("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(\d{1,5})\s*$/", $table2[3], $m)) {
                            $s->airline()
                                ->name($m[1])
                                ->number($m[2])
                            ;
                        }

                        if (preg_match("/operated by\s*(.+)/s", $table2[0], $m)) {
                            $s->airline()
                                ->operator(preg_replace("/\s+/", ' ', trim($m[1])));
                        }

                        $s->extra()
                            ->cabin(preg_replace("/\s+/", ' ', trim($table2[1])))
                            ->aircraft(preg_replace("/\s+/", ' ', trim($table2[2])));
                    }
                } elseif (count($flights) == 1) {
                    $table2Text = $this->re("/(.+ (?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\n(?:.+\n)*?)\n/", $ftext);
                    $table2Text = preg_replace("/\n .*(?:baggage|Check)[\s\S]+/", '', $table2Text);

                    $table2 = $this->createTable($table2Text, $this->rowColumnPositions($this->inOneRow($table2Text)));
                    $table2 = array_map('trim', $table2);

                    $cabinVal = $aircraftVal = $flightVal = null;

                    if (count($table2) === 3) {
                        $cabinVal = preg_replace('/\s+/', ' ', $table2[0]);
                        $aircraftVal = preg_replace('/\s+/', ' ', $table2[1]);
                        $flightVal = $table2[2];
                    } else {
                        $cabinVal = preg_match("/^\s*({$this->opt($this->t('cabinVariants'))})\s*$/i", preg_replace('/\s+/', ' ', $table2[0]), $m) ? $m[1] : null;
                        $flightVal = $table2[count($table2) - 1];
                    }

                    $s->extra()->cabin($cabinVal, false, true)->aircraft($aircraftVal, false, true);

                    if (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(\d{1,5})$/", $flightVal, $m)) {
                        $s->airline()->name($m[1])->number($m[2]);
                    }
                }

                if (strpos($ftext, 'Seat Selection') !== false && !empty($s->getDepCode()) && !empty($s->getArrCode())) {
                    if (preg_match_all("/\n *(?<traveller>.+?) +Seat *- *Seat Selection.*\[ *{$s->getDepCode()}-{$s->getArrCode()} ?- ?(?<seat>\d{1,3}[A-Z]) *\]/", $route, $m)) {
                        // Seat - Seat Selection [ MEL-DPS - 7B ]
                        // no example for 2 and more flight in segment
                        foreach ($m[0] as $i => $v) {
                            $m['traveller'][$i] = preg_replace('/^\s*(?:Mrs|Mr|Miss|Mstr|Ms)\s+/', '', $m['traveller'][$i]);
                            $s->extra()
                                ->seat($m['seat'][$i], false, false, $m['traveller'][$i]);
                        }
                    }
                }
            }
        }
    }

    public function parseTransfer(Email $email, $sText): void
    {
        $this->logger->debug(__FUNCTION__);

        $t = $email->add()->transfer();

        $t->general()
            ->noConfirmation();

        $transfers = array_filter(preg_split("/(?:^\s*Transfer\s*\n|\n) {0,20}[A-Z]{3} {1,10}Transfer\n/", $sText));

        foreach ($transfers as $tText) {
            $s = $t->addSegment();

            $tableText = $this->re("/\n *Pick-up +\.?Drop-off\s*\n([\s\S]+?\b20\d{2}\s+[\s\S]+?\b20\d{2}.*)(?:\n|$)/", $tText);
            $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));
            $table = array_filter(preg_replace(["/^\s*.\s*$/", "/^(?:\s*|.* {5,})\.(\s*| {5,}.+)$/m"], ['', '$1 $2'], $table));

            $depName = $depDate = null;
            $arrName = $arrDate = null;

            $names = array_unique(array_filter(array_column($this->travelPoints, 'name')));
            $names = preg_replace("/^(.{25}).*/m", '$1', $names);

            if (count($table) === 1) {
                if (preg_match("/^(?:\s*\n)?( *\w.+ ){$this->opt($names)}/", $table[0], $m)) {
                    $table = $this->createTable($table[0], [0, mb_strlen($m[1])]);
                }
            }

            if (count($table) === 2) {
                if (preg_match("/^([\s\S]+)\n(.+)\s*$/", $table[0], $m)) {
                    $depName = preg_replace("/\s*\n\s*/", ' ', trim($m[1]));
                    $depDate = strtotime(trim($m[2]));
                }

                if (preg_match("/^([\s\S]+)\n(.+)\s*$/", $table[1], $m)) {
                    $arrName = preg_replace("/\s*\n\s*/", ' ', trim($m[1]));
                    $arrDate = strtotime(trim($m[2]));
                }
            }

            if (!empty($depDate) && !empty($depName) && !empty($arrDate) && !empty($arrName)) {
                foreach ($this->travelPoints as $tp) {
                    if (stripos($depName, $tp['name']) !== false
                        && $depDate === strtotime('00:00', $tp['date'])
                        && $tp['type'] === 'out'
                    ) {
                        if ($tp['placeType'] === 'hotel') {
                            $s->departure()
                                ->name($depName)
                                ->address($tp['placeInfo'])
                                ->noDate()
                            ;
                        } elseif ($tp['placeType'] === 'flight') {
                            $s->departure()
                                ->name($depName)
                                ->code($tp['placeInfo'])
                                ->date($tp['date'])
                            ;
                        }
                    }

                    if (stripos($arrName, $tp['name']) !== false
                        && $arrDate === strtotime('00:00', $tp['date'])
                        && $tp['type'] === 'in'
                    ) {
                        if ($tp['placeType'] === 'hotel') {
                            $s->arrival()
                                ->name($depName)
                                ->address($tp['placeInfo'])
                                ->noDate()
                            ;
                        } elseif ($tp['placeType'] === 'flight') {
                            $s->arrival()
                                ->name($depName)
                                ->code($tp['placeInfo'])
                                ->date(strtotime('-3 hours', $tp['date']))
                            ;
                        }
                    }
                }
            }
        }
    }

    public function parseHotel(Email $email, $sText): void
    {
        $this->logger->debug(__FUNCTION__);

        $sText = preg_replace("/\n+\s*Issued on[\s\:]+.+\n\s+Itinerary Document\n+TRAVEL PLANNERS INTERNATIONAL.+\n+[}]\n+/u", "\n", $sText);
        $hotelParts = array_filter(preg_split("/(?:^\s*Accommodation\s*\n|\n) {0,10}[A-Z]{3} {1,10}Hotel\n/", $sText));

        foreach ($hotelParts as $hotelPart) {
            $hotelPart = preg_replace("/^([ ]*Issued on.+\n+\s*Itinerary Document\n+MTA.*\n[}])/m", "", $hotelPart);
            $hotelPart = preg_replace("/(\b\n)(\n\s+Issued\s*On.*\n+\s*Page.+\n+\s*Agent Invoice\n+.+Booking\s*.+\n+[}]\n+)/", "$1", $hotelPart);

            $h = $email->add()->hotel();

            if (preg_match("/({$this->opt($this->t('Booking Reference'))})[ ]*[:]+\s*([A-z\d]+)[ ]*\n/", $hotelPart, $m)) {
                $h->general()->confirmation($m[2], $m[1]);
            } else {
                $h->general()
                    ->noConfirmation();
            }

            $cancellation = $this->re("/Cancellation Policy :\n+(.+)\n+ {20,}[A-Z]{3}.*\n/s", $hotelPart);

            if (preg_match("/^(.+)\n+\s+Page\s+\d+\s+of\s+\d+/su", $cancellation, $m)) {
                $cancellation = $m[1];
            }

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation(preg_replace("/\n\s+/", ", ", $cancellation));
            }

            // $this->logger->debug($hotelPart);

            if (preg_match("/^\s*\d+\s*(?<hotelName>.+)\n\s*[\d\.]+\s*Stars\n+\s*(?<address>.+)\n\s*\d\s*X/ms", $hotelPart, $m)
            || preg_match("/^\s*\d+\s*(?<hotelName>.+)\n(?:\s*[\d\.]+\s*Stars\n+)?\s*(?<address>(?:.+\n){1,5})\n*\s*\d\s*X/", $hotelPart, $m)) {
                if (stripos($m['address'], 'Issued on') !== false) {
                    $m['address'] = preg_replace("/\s+Issued on.+/s", "", $m['address']);
                }

                if (preg_match("/^(.+)\s+Page\s+\d+\s+of\s+\d+/s", $m['address'], $match)) {
                    $m['address'] = $match[1];
                }

                $h->hotel()
                    ->name($m['hotelName'])
                    ->address(preg_replace("/\n\s*/s", ", ", $m['address']));
            }

            if (preg_match("/\n\s*\.?Check-in +Check-out +Duration\s*\n+\s*(?<checkIn>.+?) {5,}(?<checkOut>.+) +\d+ +Night/", $hotelPart, $m)) {
                $m = str_replace(".", '', $m);

                if (!empty(trim($m['checkIn'])) && !empty(trim($m['checkOut']))) {
                    $h->booked()
                        ->checkIn(strtotime($m['checkIn']))
                        ->checkOut(strtotime($m['checkOut']));
                }
            }

            if (!empty($h->getHotelName()) && !empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
                foreach ($email->getItineraries() as $it) {
                    if ($it->getType() === 'hotel' && $it->getId() !== $h->getId()) {
                        /** @var \AwardWallet\Schema\Parser\Common\Hotel $it */
                        if ($it->getHotelName() === $h->getHotelName() && $it->getCheckInDate() === $h->getCheckInDate() && $it->getCheckOutDate() === $h->getCheckOutDate()) {
                            $email->removeItinerary($h);
                            $h = $it;

                            break;
                        }
                    }
                }
            }
            $this->travelPoints[] = ['date' => $h->getCheckInDate(), 'name' => $h->getHotelName(), 'type' => 'in', 'placeType' => 'hotel', 'placeInfo' => $h->getAddress()];
            $this->travelPoints[] = ['date' => $h->getCheckOutDate(), 'name' => $h->getHotelName(), 'type' => 'out', 'placeType' => 'hotel', 'placeInfo' => $h->getAddress()];

            if (preg_match_all("/\n\s*(?<rooms>\d+) +X +(?<type>.+?)\( *(?<adult>\d+) *, *(?<child>\d+) *, *\d+ *\)/", $hotelPart, $m)) {
                $h->booked()
                    ->rooms(array_sum($m['rooms']) + $h->getRoomsCount() ?? 0)
                    ->guests(array_sum($m['adult']))
                    ->kids(array_sum($m['child']))
                ;

                foreach ($m['type'] as $key => $type) {
                    for ($i = 1; $i <= $m['rooms'][$key]; $i++) {
                        $h->addRoom()
                            ->setType($type);
                    }
                }
            }
        }
    }

    public function parseCar(Email $email, $sText): void
    {
        $this->logger->debug(__FUNCTION__);

        $car = $email->add()->rental();

        // TODO: need add
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDateRelative($date, $relativeDate)
    {
        if (empty($date) || empty($relativeDate)) {
            return null;
        }
//        $this->logger->debug('$date = ' . $date);
        $in = [
            // JUN    14
            '#^\s*([[:alpha:]]+)\s+(\d+)\s*$#ius',
        ];
        $out = [
            '$2 $1',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#^\d+ ([[:alpha:]]+)$#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }

            return EmailDateHelper::parseDateRelative($date, $relativeDate);
        }

        return null;
    }

    private function split($re, $text): array
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
