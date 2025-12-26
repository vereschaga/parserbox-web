<?php

namespace AwardWallet\Engine\sunwing\Email;

use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Pdf extends \TAccountChecker
{
    public $mailFiles = "sunwing/it-103189869.eml, sunwing/it-152760874.eml, sunwing/it-161989377.eml, sunwing/it-163774484.eml, sunwing/it-32113856.eml, sunwing/it-32113865.eml, sunwing/it-32152857.eml, sunwing/it-32981025.eml, sunwing/it-387721367.eml, sunwing/it-388530338.eml, sunwing/it-414668285.eml, sunwing/it-86664335.eml";

    private $pdfName = '.*\.pdf';

    private $langDetectors = [
        'en' => ['Selected Flight - Reservation Code', 'Selected Hotel'],
    ];

    private $from = '/[\.@]sunwing\.ca/i';

    private $subjects = [
        'en' => ['Invoice for Reservation'],
    ];

    private $lang = 'en';

    private $travellers = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfName);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
            $this->assignLang($text);
            $this->parsePdf($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Vacation Express') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfName);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            // Detect Provider
            if (stripos($text, 'vacationexpress') === false) {
                continue;
            }

            // Detect Format
            if ($this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parsePdf(Email $email, string $text): void
    {
        $email->obtainTravelAgency(); // because Vacation Express is a tour operator

        $tripInfo = $this->cutText('Trip Information', 'Package Summary', $text);

        $confs = array_column($email->getTravelAgency()->getConfirmationNumbers(), 0);

        if (preg_match('/(Booking[ ]*#)[ ]*:[ ]*(\w+)/', $tripInfo, $m) && !in_array($m[2], $confs)) {
            $email->ota()->confirmation($m[2], $m[1]);
        } else {
            $otaConfirmation = $this->re("/Vacation Express Reservation [#]\:\s*(\d+)/", $text);

            if (!empty($otaConfirmation) && !in_array($otaConfirmation, $confs)) {
                $email->ota()->confirmation($otaConfirmation, 'Vacation Express Reservation');
            }
        }

        if (preg_match('/\n *Total Package Price[ ]*(\D+?)[ ]*(\d[,.\'\d]*)(?: {3,}|\n|$)/', $text, $m)) {
            $email->price()
                ->currency(str_replace('$', 'USD', $m[1]))
                ->total($m[2]);
        } elseif (preg_match('/Total Price:[ ]*(\D+?)[ ]*(\d[,.\'\d]*)(?: {3,}|\n|$)/', $tripInfo, $m)) {
            $email->price()
                ->currency(str_replace('$', 'USD', $m[1]))
                ->total($m[2]);
        } elseif (preg_match('/Total Due:[ ]*(\D+?)[ ]*(\d[,.\'\d]*)(?: {3,}|\n|$)/', $tripInfo, $m)) {
            $email->price()
                ->currency(str_replace('$', 'USD', $m[1]))
                ->total($m[2])
            ;
        }

        $passengersText = $this->cutText('Package Summary', ['Selected Flight', 'Selected Hotel'], $text);

        if (preg_match_all('/([A-Z \-]+)(?: - Child[ ]+\[\d{1,2}\])?[ ]{2,}\d{1,2} \w+ \d{2,4}/', $passengersText, $m)) {
            $this->travellers = $m[1];
        } elseif (preg_match("/^(?:.*\n+){0,10} {0,10}Dear((?: +[A-Z \-]+\n)+)\n/", $text, $m)) {
            $this->travellers = array_map('trim', explode("\n", trim($m[1])));
        } else {
            $this->travellers[] = $this->re("/Client Names:\s*([A-Z \-]+)/", $text);
        }
        $this->travellers = array_unique(array_filter($this->travellers));

        $flightTexts = [];
        $pos = stripos($text, 'Selected Flight');
        $i = 0;
        $textAlt = $text;

        while ($pos !== false && $i < 4) {
            $str = $this->re("/^[ ]*{$this->opt('Selected Flight')}([\s\S]*?)\n+.*{$this->opt(['Roundtrip air', 'Selected Hotel', 'Flight Information', 'Pricing & Services'])}/m", $textAlt);
            $flightTexts[] = $str;
            $textAlt = substr($textAlt, $pos + strlen($str));
            $pos = stripos($textAlt, 'Selected Flight');
            $i++;
        }

        if (!empty($flightTexts)) {
            $this->parseFlight($email, $text, $flightTexts);
        }

        $hotelText = $this->cutText('Hotel Information', ['Transfer Information'], $text);

        if (!empty($hotelText)) {
            $this->parseHotel2($email, $hotelText);
        } else {
            $hotelText = $this->cutText('Selected Hotel', ['Pricing & Services', 'Services', 'Selected Additional Services'], $text);

            if (!empty($hotelText)) {
                $this->parseHotel($email, $hotelText);
            }
        }

        $transferText = $this->cutText('Transfer Information', ['Arrival Info:', 'Arrival Information:'], $text);

        if (!empty($transferText)) {
            $this->parseTransfer($email, $transferText);
        }
    }

    private function parseFlight(Email $email, string $text, array $flightTexts): void
    {
        $flightInfos = $this->cutText('Flight Information', ['Selected Hotel', 'Pricing & Services', 'Services'], $text);

        $infos = $this->splitter("/(?:^|\n) {0,5}([A-Z\d]{5,7}\n)/", $flightInfos, true);

        if (count($infos) == 0) {
            $infos = $this->splitter("/\s*VE GDS Code\:\s*([A-Z\d]{5,7}\n)/", $flightInfos, true);
        }

        foreach ($flightTexts as $key => $flightText) {
            $newTravellers = [];

            $f = $email->add()->flight();

            $info = '';

            if (count($infos) == count($flightTexts)) {
                $info = $infos[$key];
            }

            if (preg_match('/(Reservation Code):[ ]*([A-Z\d]{5,})\b/', $flightText, $m)) {
                $f->general()->confirmation($m[2], $m[1]);
            } elseif (preg_match('/^([A-Z\d]{5,7})\n/', $info, $m)) {
                $f->general()->confirmation($m[1]);
            } else {
                $f->general()
                    ->noConfirmation();
            }

            if (preg_match_all('/ {3,}((?:\d{3}| )[- ]*\d{10}) +- +([A-Z \-]+?)( {2,}|$)/m', $info, $m)) {
                $f->general()->travellers($m[2]);

                foreach ($m[1] as $number) {
                    $f->addTicketNumber(str_replace([' ', '-'], '', $number), false);
                }
            } elseif (count($this->travellers)) {
                $f->general()->travellers($this->travellers);
            }

            if (preg_match('/Airline Record Locator\(s\): *(.+(?:\n {20,}\S+.+)?)Flight/', $info, $m)
                || preg_match('/Airline Record Locator\(s\): *(.+(?:\n {20,}\S+.+)?)(?:\n|$)/', $info, $m)) {
                $flLocators = explode("\n", trim($m[1]));

                foreach ($flLocators as $flLocator) {
                    if (preg_match("/^\s*([A-Z\d]{2}) *- *([A-Z\d]{5,7})/", $flLocator, $mat)) {
                        $airlineRLs[$mat[1]] = $mat[2];
                    } else {
                        $airlineRLs[] = '';
                    }
                }
            }

            $hasSeats = false;

            if (preg_match("/^(.* {2,})Flight Info/m", $flightText, $m)) {
                $airlinePos = strlen($m[1]);
            }

            if (preg_match("/^(.*\n+){0,3}.* {2,}Seats/", $flightText)) {
                $hasSeats = true;
            }

            $flightText = preg_replace("/^(?:[\s\S]*)? *Departure {2,}Date .*/", '', $flightText);
            $flightTextRows = explode("\n", $flightText);

            $segments = $segRows = [];

            foreach ($flightTextRows as $row) {
                $segRows[] = $row;
                $segText = implode("\n", $segRows);

                $tablePos = [0];

                if (preg_match("/^(.+? )\d{1,2}[ ]+[[:alpha:]]{3}[ ]+\d{2,4}[ ]+\d{1,2}:\d{2}(?:\b|\D)/mu", $segText, $matches)) {
                    // 22 Mar 25   8:45
                    $tablePos[] = mb_strlen($matches[1]);
                }

                $table = count($tablePos) === 2 ? $this->splitCols($segText, $tablePos) : [];

                /*
                    New York(JFK), NY (JFK) -
                                                                            Delta, DL
                    Antigua, Antigua & Barbuda  22 Mar 25   8:45AM-1:17PM                W    0    29D, 29E
                                                                            # 1954
                    (ANU)
                */

                if (count($table) === 2 && preg_match("/\(\s*[A-Z]{3}\s*\)\s+[-]+\s[\s\S]*\(\s*[A-Z]{3}\s*\)\s*$/", $table[0])
                    && preg_match("/.{20} #[ ]+(\d{1,5})\b/", $segText)
                ) {
                    $segments[] = $segText;
                    $segRows = [];
                }
            }

            if (count($segRows) > 0) {
                $this->logger->debug('Wrong flight segments table!');

                return;
            }

            foreach ($segments as $i => $segment) {
                if (empty(trim($segment))) {
                    continue;
                }

                $s = $f->addSegment();

                $tableText = preg_replace('/\b(?:FLIGHT OPERATED BY|Operated By:)[\s\S]+/', '', $segment);

                $pos = $this->rowColsPos($this->inOneRow($tableText));

                $table = $this->splitCols($tableText, $pos, false, true);

                if (preg_match("/^\s*\d{1,2} +[[:alpha:]]{3} +\d{2}\s*$/", $table[1], $m)
                    && preg_match("/^\s*\d+:\d+[AP]M ?- ?\d+:\d+[AP]M\s*$/", $table[2], $m)
                ) {
                    $table[1] = $this->unionColumns($table[1], $table[2]);
                    unset($pos[2]);
                    unset($table[2]);
                    $table = array_values($table);
                } elseif (
                    preg_match("/^(?<c3>(?<c2>(?<c1>.* )\d{1,2} +[[:alpha:]]{3} +\d{2} +\d+:\d+[AP]M ?- ?\d+:\d+[AP]M))/m", $tableText, $m)) {
                    $p1 = mb_strlen($m['c1']);
                    $p2 = mb_strlen($m['c2']);
                    $p3 = min(array_map('mb_strlen', $this->res("/^(.{{$p2}} +)\S+/m", $tableText)));

                    $pos[] = $p1;
                    $pos = array_unique($pos);

                    foreach ($pos as $i => $p) {
                        if ($p > $pos[0] && $p < $p1) {
                            unset($pos[$i]);
                        }

                        if ($p > $p1 && $p < $p2) {
                            unset($pos[$i]);
                        }
                    }
                    sort($pos);

                    foreach ($pos as $i => $p) {
                        if ($p > $pos[1] && $p <= $p3) {
                            unset($pos[$i]);
                        }
                    }
                    $pos[] = $p2;

                    sort($pos);
                    $table = $this->splitCols($tableText, $pos, false, true);
                }

                if (preg_match("/\d+:\d+[AP]M ?- ?\d+:\d+[AP]M\s*$/", $table[1] ?? '')
                    && strpos($table[2] ?? '', '#') === false
                    && strpos($table[3] ?? '', '#') !== false
                ) {
                    $table[2] = $this->unionColumns($table[2], $table[3]);
                    unset($table[3]);
                    $table = array_values($table);
                }

                if (preg_match("/^\s*[^#]+ #\s*$/", $table[2] ?? '')
                    && preg_match("/^\s*\d{1,5}\s*$/", $table[3] ?? '')
                ) {
                    $table[2] = $this->unionColumns($table[2], $table[3]);
                    unset($table[3]);
                    $table = array_values($table);
                }

                if (preg_match("/^(\s*.+ # *\d+) ([A-Z]{1,2})\s*$/", $table[2] ?? '', $m)) {
                    $table[2] = $m[1];
                    array_splice($table, 3, 0, [$m[2]]);

                    $table = array_values($table);
                }

                if (!$this->isCorrectColumns(count($table), $hasSeats)) {
                    $f->addSegment();
                }

                $foundAirlineConf = false;
                $airlines = [];

                if ($this->isCorrectColumns(count($table), $hasSeats) === true) {
                    if (preg_match("/^\s*(?<dName>.+?)\s*\((?<dCode>[A-Z]{3})\)\s+-\s+(?<aName>.+?)\s*\((?<aCode>[A-Z]{3})\)/",
                        preg_replace('/\s+/', ' ', $table[0]), $m)
                    || preg_match("/^\s*(?<dName>.+?)\s*\((?<dCode>[A-Z]{3})\)\s+-\s+(?<aName>.+?)$/",
                            preg_replace('/\s+/', ' ', $table[0]), $m)
                    ) {
                        $s->departure()
                            ->name($m['dName'])
                            ->code($m['dCode']);
                        $s->arrival()
                            ->name($m['aName']);

                        if (!isset($m['aCode'])) {
                            $s->arrival()
                                ->noCode();
                        } else {
                            $s->arrival()
                                ->code($m['aCode']);
                        }
                    }

                    if (preg_match("/^\s*(\d{1,2})\s+([[:alpha:]]{3,6})\s+(\d{2})\s+(\d{1,2}:\d{2}(?:[ap]m))\s*-\s*(\d{1,2}:\d{2}(?:[ap]m))\s*$/i",
                        preg_replace('/\s+/', ' ', $table[1]), $m)) {
                        $s->departure()
                            ->date(strtotime($m[1] . ' ' . $m[2] . ' 20' . $m[3] . ', ' . $m[4]));
                        $s->arrival()
                            ->date(strtotime($m[1] . ' ' . $m[2] . ' 20' . $m[3] . ', ' . $m[5]));
                    }

                    if (preg_match("/^\s*(?<name>.+?)\s*(?:,\s*(?<code>[A-Z\d][A-Z]|[A-Z][A-Z\d]))?\s*#\s*(?<number>\d{1,5})\s*$/",
                        preg_replace('/\s+/', ' ', $table[2]), $m)) {
                        $s->airline()
                            ->name(empty($m['code']) ? $m['name'] : $m['code'])
                            ->number($m['number']);
                        $airlines[] = $s->getAirlineName();

                        if (isset($airlineRLs[empty($m['code']) ? $m['name'] : $m['code']])) {
                            $s->airline()->confirmation($airlineRLs[empty($m['code']) ? $m['name'] : $m['code']]);
                            $foundAirlineConf = true;
                        }

                        if (preg_match("/\b(?:FLIGHT OPERATED BY|Operated By:) (.+) (?:DBA|AS)/", $segment, $m)) {
                            $s->airline()
                                ->operator(trim($m[1], '-'));
                        }
                    }

                    if (preg_match("/^\s*([A-Z]{1,2})\s*$/", trim($table[3]), $m)) {
                        $s->extra()
                            ->bookingCode($m[1]);
                    }

                    if (preg_match("/^\s*(\d+)\s*$/", trim($table[4]), $m)) {
                        $s->extra()
                            ->stops($m[1]);
                    }

                    if ($hasSeats == true) {
                        if (!empty($addSeats)) {
                            $table[5] = $addSeats . "\n" . ($table[5] ?? '');
                        }
                        $addSeats = null;

                        if (preg_match("/^\s*(?<seat1>\d{1,3}[A-Z](\s*,\s*\d{1,3}[A-Z])*)\s*\n(?<seat2>\d{1,3}[A-Z](\s*,\s*\d{1,3}[A-Z]),?)\s*$/", $table[5] ?? '', $m)
                            && isset($segments[$i + 1])) {
                            $table[5] = $m['seat1'];
                            $addSeats = $m['seat2'];
                        }
                        $seats = array_filter(preg_split("/\s*,\s*/", trim(preg_replace('/\s+/', '', $table[5] ?? ''))));

                        if (count($seats) == count(array_filter($seats, function ($v) {
                            if (preg_match("/^\d{1,3}[A-Z]$/", $v)) {
                                return true;
                            }

                            return false;
                        }))) {
                            $s->extra()
                                ->seats($seats);
                        }

                        if (empty($s->getSeats())) {
                            $newSeats = [];

                            foreach ($seats as $seat) {
                                if (preg_match("/^\s*(\d+):(\d{1,3}[A-Z])\s*$/", $seat, $m)) {
                                    if (isset($f->getTravellers()[$m[1] - 1])) {
                                        $newTravellers[] = $f->getTravellers()[$m[1] - 1][0];
                                    }
                                    $newSeats[] = $m[2];
                                }
                            }

                            if (!empty($newSeats)) {
                                $s->extra()->seats($newSeats);
                            }
                        }
                    }
                }
            }

            if (isset($airlineRLs) && $foundAirlineConf == false && !empty(array_filter($airlineRLs)) && count(array_unique($airlineRLs)) == 1 && count($airlineRLs) == 1) {
                $conf = array_shift($airlineRLs);

                foreach ($f->getSegments() as $seg) {
                    $seg->airline()
                        ->confirmation($conf);
                }
            }

            if (!empty($newTravellers)) {
                $newTravellers = array_unique($newTravellers);

                foreach ($f->getTravellers() as $tr) {
                    $f->removeTraveller($tr[0]);
                }
                $f->setTravellers($newTravellers, true);
            }
        }
    }

    private function isCorrectColumns($tableCount, $hasSeats)
    {
        if (
            (($tableCount === 5 || $tableCount === 6) && $hasSeats == true)
            || ($tableCount === 5 && $hasSeats == false)
        ) {
            return true;
        }

        return false;
    }

    private function parseHotel(Email $email, string $text): void
    {
        $h = $email->add()->hotel();

        $h->general()
            ->noConfirmation();

        if (0 < count($this->travellers)) {
            $h->general()
                ->travellers($this->travellers);
        }

        if ($hotelName = $this->re('/Selected Hotel\s+(.+(?:\n {0,5}\S.+)?)/', $text)) {
            $h->hotel()
                ->name(preg_replace('/\s+/', ' ', trim($hotelName, '-')))
                ->noAddress()
            ;
        }

        if (preg_match_all('/for (?<adult>\d{1,2}) adults?(?: *(?<kids>\d{1,2}) child)?.*(?<room>\d{1,2}) room\s+\-[ ]*(?<type>.+)/', $text, $m)) {
            $h->booked()
                ->guests(array_sum($m['adult']))
                ->kids(array_sum(array_filter($m['kids'] ?? [])))
                ->rooms(array_sum($m['room']));

            foreach ($m['type'] as $type) {
                $r = $h->addRoom();
                $r->setDescription($type);
            }
        }

        if (preg_match('/Check in Date:[ ]*(\d{1,2} \w+ \d{2,4}), Check out Date:[ ]*(\d{1,2} \w+ \d{2,4})/', $text, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime($m[2]));
        }
    }

    private function parseHotel2(Email $email, string $htext): void
    {
        $hotelTexts = array_filter(array_map('trim', preg_split("/(?:^|\n) *Hotel Information *\n/", $htext)));

        foreach ($hotelTexts as $text) {
            $headerPart = $this->re("/^\s*([\s\S]+?)\n\s*Arrival Date:/", $text);
            $headerTable = ['', '', '', ''];
            $pos = $this->rowColsPos($this->re("/^(\s*(\S ?)+ {2,}(\S ?)+ {2,}(\S ?)+ {2,}( ?\S)+\s*)$/m", $text));

            if (empty(array_filter($pos)) && preg_match("/^(?<c3>(?<c2>(?<c1> *(?:\S ?)+ *: *)(?:\S ?)+ {2,})(?:\S ?)+ *: +)(?: ?\S)+ *$/m", $text, $m)) {
                $pos = [0, strlen($m['c1']), strlen($m['c2']), strlen($m['c3'])];
            }

            if (count($pos) === 4) {
                $headerTable = $this->splitCols($headerPart, [0, $pos[2]]);
            }

            $h = $email->add()->hotel();

            $h->general()
                ->noConfirmation();

            if (0 < count($this->travellers)) {
                $h->general()
                    ->travellers($this->travellers);
            }

            $h->hotel()
                ->name(preg_replace('/\s+/', ' ', $this->re("/Hotel Name:\s+(.+?)\s*\n\s*Address:/s", $headerTable[0])));

            $h->hotel()
                ->address(preg_replace('/\s+/', ' ', $this->re("/Address:\s+(.+?)\s*\n\s*Local Phone:/s", $headerTable[0])))
                ->phone(preg_replace('/\s+/', ' ', $this->re("/Local Phone:\s+(.*?)\s*$/s", $headerTable[0])), true, true);

            if (preg_match('/Accommodations\: +(\d+) room\(s\)\,\s*(.+)/', $text, $m)) {
                $h->booked()
                    ->rooms($m[1]);
                $r = $h->addRoom();
                $r->setDescription($m[2]);
            }

            if (preg_match('/Arrival Date:[ ]*(\w*\s*\w*\s*\d{1,2}\,\s*\d{2,4})\s*Departure Date:[ ]*(\w*\s*\w*\s*\d{1,2}\,\s*\d{2,4})/s',
                $text, $m)) {
                $h->booked()
                    ->checkIn(strtotime($m[1]))
                    ->checkOut(strtotime($m[2]));
            }

            foreach ($email->getItineraries() as $key => $it) {
                if ($it->getType() !== 'hotel') {
                    continue;
                }
                $array = ['hotelName', 'address', 'checkInDate', 'checkOutDate'];
                $itArr = array_intersect_key($it->toArray(), $array);
                $hArr = array_intersect_key($h->toArray(), $array);

                if ($h->getId() !== $it->getId() && $itArr === $hArr) {
                    /** @var Hotel $it */
                    if (!empty($h->getRoomsCount())) {
                        $it->booked()
                            ->rooms(($it->getRoomsCount() ?? 0) + $h->getRoomsCount());
                    }

                    if (!empty($h->getGuestCount())) {
                        $it->booked()
                            ->guests(($it->getGuestCount() ?? 0) + $h->getGuestCount());
                    }

                    if (!empty($h->getKidsCount())) {
                        $it->booked()
                            ->kids(($it->getKidsCount() ?? 0) + $h->getKidsCount());
                    }

                    foreach ($h->getRooms() as $room) {
                        $it->addRoom()->setDescription($room->getDescription());
                    }
                    $email->removeItinerary($h);
                }
            }
        }
    }

    private function parseTransfer(Email $email, string $text): void
    {
        $t = $email->add()->transfer();

        $t->general()
            ->noConfirmation()
            ->travellers($this->travellers);

        $segments = [];

        if (preg_match_all("/(?:Date\\/Service|Departure Date): *(.+(?:\n {10,}.+)*)\n/", $text . "\n", $m)) {
            $segments = preg_replace('/\s+/', ' ', $m[1]);
        }

        foreach ($segments as $stext) {
            $stext = trim($stext);

            if (preg_match("/" . str_replace(' ', '\s+', "transfer not scheduled until flight info provided") . "/", $stext)) {
                continue;
            }
            $s = $t->addSegment();

            $date = strtotime($this->re("/^(.+?) - /", $stext));
            $routeText = $this->re("/^.+? - (.+? to .+?)(?:,[^,]*[Tt]ransfer|$)/", $stext);

            if (empty($routeText)) {
                $routeText = $this->re("/^.+? - (.+ to .+)/", $stext);
            }
            $routes = explode(' to ', $routeText);

            if (count($routes) == 2) {
                $aiportCodeRe1 = "/^\s*(?<code>[A-Z]{3}) airport/";
                $aiportCodeRe2 = "/^\s*(?<dcode>[A-Z]{3})-(?<acode>[A-Z]{3}) /";
                $aiportCodeRe3 = "/^\s*(?<dcode>[A-Z]{3}) for .+ Flight #\d+\s*@/";
                $aiportCodeRe4 = "/^\s*(?<acode>[A-Z]{3}) from .+ Flight #\d+\s*@/";

                if (preg_match($aiportCodeRe1, $routes[0], $m) || preg_match($aiportCodeRe2, $routes[0], $m)
                    || preg_match($aiportCodeRe3, $routes[0], $m) || preg_match($aiportCodeRe4, $routes[0], $m)
                ) {
                    $s->departure()
                        ->code($m['code'] ?? $m['acode']);
                } else {
                    $s->departure()
                        ->name($routes[0]);
                    $address = $this->findHotelAddress($routes[0], $email);

                    if (!empty($address)) {
                        $s->departure()
                            ->address($address);
                    }
                }

                if (preg_match($aiportCodeRe1, $routes[1], $m) || preg_match($aiportCodeRe2, $routes[1], $m)
                    || preg_match($aiportCodeRe3, $routes[1], $m) || preg_match($aiportCodeRe4, $routes[1], $m)
                ) {
                    $s->arrival()
                        ->code($m['code'] ?? $m['dcode']);
                } else {
                    $s->arrival()
                        ->name($routes[1]);
                    $address = $this->findHotelAddress($routes[1], $email);

                    if (!empty($address)) {
                        $s->arrival()
                            ->address($address);
                    }
                }

                $timeReg = "/ (?:Flight #|(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])) ?\d{1,5} @ (?<t1>\d{1,2}):?(?<t2>\d{2}(?: *[apAP][mM])?)\s*$/";

                if ($date && preg_match($timeReg, $routes[0], $m)) {
                    $s->departure()
                        ->date(strtotime("+30 minutes", strtotime($m['t1'] . ':' . $m['t2'], $date)));
                    $s->arrival()
                        ->noDate();
                }

                if ($date && preg_match($timeReg, $routes[1], $m)) {
                    $s->departure()
                        ->noDate();
                    $s->arrival()
                        ->date(strtotime("-3 hours", strtotime($m['t1'] . ':' . $m['t2'], $date)));
                }

                if ((!empty($s->getDepName()) || !empty($s->getDepCode()))
                    && (!empty($s->getArrName()) || !empty($s->getArrCode()))
                    && empty($s->getDepDate()) && empty($s->getArrDate())
                    && preg_match("/^\D+$/", implode('', $routes))
                ) {
                    $t->removeSegment($s);
                }
            }
        }

        if (count($t->getSegments()) === 0) {
            $email->removeItinerary($t);
        }
    }

    private function findHotelAddress($hotelName, Email $email)
    {
        foreach ($email->getItineraries() as $it) {
            if ($it->getType() == 'hotel') {
                /** @var Hotel $it */
                if ($it->getHotelName() === $hotelName) {
                    return $it->getAddress();
                }
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function res($re, $str, $c = 1): ?array
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }

            return '';
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function splitter($regular, $text, $deleteFirst = false)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($deleteFirst === true) {
            array_shift($array);
        }

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
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

    private function rowColsPos($row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function unionColumns($col1, $col2)
    {
        $col1Rows = explode("\n", $col1);
        $col2Rows = explode("\n", $col2);
        $newCol = '';

        for ($c = 0; $c < max(count($col1Rows), count($col2Rows)); $c++) {
            $newCol .= ($col1Rows[$c] ?? '') . ' ' . ($col2Rows[$c] ?? '') . "\n";
        }

        return $newCol;
    }

    // private function splitCols($text, $pos = false, $trim = true, $correct = false)
    // {
    //     $ds = 8;
    //     $cols = [];
    //     $rows = explode("\n", $text);
    //
    //     if (!$pos) {
    //         $pos = $this->rowColsPos($rows[0]);
    //     }
    //     arsort($pos);
    //
    //     foreach ($rows as $row) {
    //         foreach ($pos as $k=>$p) {
    //             if ($correct == true) {
    //                 if ($k != 0 && (!empty(trim(mb_substr($row, $p - 1, 1))) || !empty(trim(mb_substr($row, $p + 1, 1))))) {
    //                     $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');
    //
    //                     if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
    //                         $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
    //                         $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
    //                         $pos[$k] = $p - strlen($m[2]) - 1;
    //
    //                         continue;
    //                     } else {
    //                         $str = mb_substr($row, $p, $ds, 'UTF-8');
    //
    //                         if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
    //                             $cols[$k][] = trim($m[2] . mb_substr($row, $p + $ds, null, 'UTF-8'));
    //                             $row = mb_substr($row, 0, $p, 'UTF-8') . $m[1];
    //                             $pos[$k] = $p + strlen($m[1]) + 1;
    //
    //                             continue;
    //                         } elseif (preg_match("#^\s+(.*)#", $str, $m)) {
    //                             $cols[$k][] = trim($m[1] . mb_substr($row, $p + $ds, null, 'UTF-8'));
    //                             $row = mb_substr($row, 0, $p, 'UTF-8');
    //
    //                             continue;
    //                         } elseif (!empty($str)) {
    //                             $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');
    //
    //                             if (preg_match("#(\S*)\s+(\S*)$#", $str, $m)) {
    //                                 $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
    //                                 $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
    //                                 $pos[$k] = $p - strlen($m[2]) - 1;
    //
    //                                 continue;
    //                             }
    //                         }
    //                     }
    //                 }
    //             }
    //
    //             if ($trim === true) {
    //                 $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
    //             } else {
    //                 $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
    //             }
    //             $row = mb_substr($row, 0, $p, 'UTF-8');
    //         }
    //     }
    //     ksort($cols);
    //
    //     foreach ($cols as &$col) {
    //         $col = implode("\n", $col);
    //     }
    //
    //     return $cols;
    // }
}
