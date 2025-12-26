<?php

namespace AwardWallet\Engine\travelinc\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "travelinc/it-2441813.eml, travelinc/it-2795142.eml, travelinc/it-2842274.eml, travelinc/it-2843710.eml, travelinc/it-2843723.eml, travelinc/it-3987600.eml, travelinc/it-3987604.eml, travelinc/it-59667236.eml, travelinc/it-59720740.eml, travelinc/it-6377939.eml, travelinc/it-6380973.eml, travelinc/it-6380990.eml, travelinc/it-6380998.eml, travelinc/it-6381019.eml, travelinc/it-6381023.eml, travelinc/it-728429503.eml";

    public static $dictionary = [
        'en' => [
            //hotel
            'Tel:'                 => ['Tel:', 'Phone:'],
            'confNumber'           => ['Booking Code (PNR):', 'Booking Code (PNR) :'],
            'Rate per night:'      => ['Rate per night:', 'Rate:'],
            'Cancellation Policy:' => ['Cancellation Policy:', 'Cancel Policy:'],
            'otaNumber'            => ['Agency Record Locator:', 'Booking Ref.:', 'World Travel Record Locator:'],
            //flight
            'Record Locator:' => ['Record Locator:', 'Record Locator :', 'Check In Confirmation:', 'Booking Reference:'],
        ],
    ];

    public static $providers = [
        'travelinc' => ['World Travel', 'Safe Harbors Business Travel'],
        'frosch'    => ['www.frosch.com', '@frosch.com'],
    ];

    private $from = '/[@.]worldtravelinc/i';

    private $detects = [
        'World Travel Record Locator',
        'Booking Ref.',
        'Agency Record Locator:',
    ];

    private $passengers = [];

    private $lang = 'en';

    private $otaNumber;

    private $year;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email, $parser->getHeader('subject'), $parser);

        $body = $parser->getHTMLBody() . $parser->getPlainBody();

        foreach (self::$providers as $code => $pDetects) {
            foreach ($pDetects as $detect) {
                if (stripos($body, $detect) !== false) {
                    $email->setProviderCode($code);

                    break;
                }
            }
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() . $parser->getPlainBody();

        $detectedProvider = false;

        foreach (self::$providers as $code => $pDetects) {
            foreach ($pDetects as $detect) {
                if (stripos($body, $detect) !== false) {
                    $detectedProvider = true;

                    break;
                }
            }
        }

        if ($detectedProvider === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect) || 0 < $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect}')]")->length) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    public function niceTravellers($names)
    {
        $names = preg_replace(["/\s+(Mr|Ms|Mstr|Miss|Mrs)\s*$/i", "/^\s*(Mr|Ms|Mstr|Miss|Mrs)\s+/i"], ['', ''], $names);
        $names = preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1', $names);

        if (is_string($names)) {
            $names = ucwords(strtolower($names));
        } else {
            $names = array_map(function ($v) {return ucwords(strtolower($v)); }, $names);
        }

        return $names;
    }

    private function parseEmail(Email $email, string $subj = '', PlancakeEmailParser $parser): Email
    {
        if ($pax = trim($this->re("#\bfor\s+(.+?)\s*(?:\s+departing|, Departs)\s+\d#i", $subj))) {
            $this->passengers = preg_split('/\s*,\s*/', $pax);
        }

        if (empty($this->passengers) && ($pax = $this->http->FindSingleNode("(//text()[{$this->eq(['Traveler:', 'Travel Information', 'Passenger(s):'])}])[1]/following::text()[string-length(normalize-space(.))>2][1]", null, true, "#\w+/\w+#"))) {
            $this->passengers[] = $pax;
        }
        $this->passengers = $this->niceTravellers($this->passengers);

        $total = $this->http->FindSingleNode("//td[{$this->eq('Total Charges:')}][not(.//td)]/following-sibling::td[normalize-space(.)][1]");

        if (preg_match('/([A-Z]{3})[ ]+([\d\.]+)/', $total, $m)) {
            $email->price()
                ->total($m[2])
                ->currency($m[1]);
        }

        $plain = '';

        $xpath = "//a[starts-with(normalize-space(.), 'HOTEL -') or starts-with(normalize-space(.), 'AIR -') or starts-with(normalize-space(.), 'CAR -') or starts-with(normalize-space(.), 'RAIL -')]/ancestor::table[contains(normalize-space(.), 'Address') or contains(normalize-space(.), 'Depart:') or contains(normalize-space(.), 'Pick Up:')][1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->info("Itineraries does not found by xpath: {$xpath}");
            $plain = $parser->getPlainBody();
        }

        // otaNumber
        $this->otaNumber = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('otaNumber'))}][1]/following::text()[normalize-space()][1])[1]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/ui");

        if (empty($this->otaNumber)) {
            $this->otaNumber = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('otaNumber'))}][1])[1]",
                null, true, "/{$this->opt($this->t('otaNumber'))}\s*([A-Z\d]{5,})\b/ui");
        }

        if (empty($this->otaNumber) && !empty($plain)) {
            $this->otaNumber = $this->re("/Record\s+Locator\:\s*(\w+)\s*/ui", $plain);
        }
        $email->ota()
            ->confirmation($this->otaNumber);

        $anchor = false;
        /**
         * @var \DOMNode $root
         */
        foreach ($roots as $k => $root) {
            if (false !== strpos($root->nodeValue, 'HOTEL -')) {
                $this->parseHotel($email, $root);
                $anchor = true;
            }

            if (false !== strpos($root->nodeValue, 'AIR -')) {
                $this->parseAir($email, $root);
                $anchor = true;
            }

            if (false !== strpos($root->nodeValue, 'RAIL -')) {
                $this->parseRail($email, $root);
                $anchor = true;
            }

            if (false !== strpos($root->nodeValue, 'CAR -')) {
                $this->parseCar($email, $root);
                $anchor = true;
            }

            if (!$anchor) {
                $this->logger->debug("Does not found method to parser this text[{$k}]: {$root->nodeValue}");
            }
        }

        $plainSegs = $this->splitter('/((?:CAR|AIR|RAIL|HOTEL) - \w+, \w+ \d{1,2},? \d{4})/iu', $plain);

        foreach ($plainSegs as $plainSeg) {
            if (false !== strpos($plainSeg, 'HOTEL')) {
                $this->parseHotel($email, null, $plainSeg);
                $anchor = true;
            }

            if (false !== strpos($plainSeg, 'AIR')) {
                $this->parseAir($email, null, $plainSeg);
                $anchor = true;
            }

            if (false !== strpos($plainSeg, 'RAIL')) {
                $this->parseRail($email, null, $plainSeg);
                $anchor = true;
            }

            if (false !== strpos($plainSeg, 'CAR')) {
                $this->parseCar($email, null, $plainSeg);
                $anchor = true;
            }

            if (!$anchor) {
                $this->logger->debug("Does not found method to parser this text[{$k}]: {$plainSeg}");
            }
        }

        return $email;
    }

    private function parseCar(Email $email, ?\DOMNode $root = null, ?string $plain = null): void
    {
        $text = '';

        if (!empty($root)) {
            $text = implode("\n", $this->http->FindNodes("descendant::text()[normalize-space(.)]", $root));
        }

        if (empty($text) && !empty($plain)) {
            $text = $plain;
        }

        $r = $email->add()->rental();

        $this->year = $this->re('/car\s+\-\s+\w+\s+\w+\s+\d+\s+(\d+)/i', $text);

        if ($conf = $this->re("#Confirmation:\s*([\w\-]+)\s*#", $text)) {
            $r->general()
                ->confirmation($conf);
        }

        // if ($tripNum = $this->re("#World Travel Record Locator:\s*([\w\-]+)\s*#", $text)) {
        //     $r->ota()
        //         ->confirmation($tripNum);
        // }

        if (preg_match("#Drop\s+Off:\s*(?<DropoffLocation>.+?);?\s+Tel(?:ephone)?:\s*(?<DropoffPhone>[+\-0-9\s()]+?);?(?:\s+Fax:\s*(?<DropoffFax>[\+\-0-9 \(\)]+?))?(?:\s+(?:Weather|Map|Security)\s*\|?)*\s+(?<DropoffDatetime>(?:[A-Z]|\d+:\d+).+?\d{4})\s#si", $text, $m)) {
            $r->dropoff()
                ->location($this->rplSpace($m['DropoffLocation']))
                ->phone($m['DropoffPhone']);

            if (!empty($this->re("/(\d+\:\d+\sA?P?M)/", $m['DropoffDatetime']))) {
                $r->dropoff()
                    ->date(strtotime($m['DropoffDatetime']));
            }

            if (!empty($m['DropoffFax'])) {
                $r->dropoff()
                    ->fax($m['DropoffFax']);
            }
        }

        if (empty($r->getDropOffDateTime())) {
            if (preg_match("/{$this->opt($this->t('Drop Off:'))}\n(?<dropDate>[\d\:]+\sA?P?M\,\s*\w+\,\s*\w+\s\d{1,2})\n(?<dropLoc>.+)\s{$this->opt($this->t('Tel:'))}/", $text, $m)) {
                $r->dropoff()
                    ->date($this->normalizeDate($m['dropDate']))
                    ->location($m['dropLoc']);
            }
        }

        if (preg_match("#Pick\s+Up:\s*(?<PickupLocation>.+?);?\s+Tel(?:ephone)?:\s*(?<PickupPhone>[\+\-0-9 \(\)]+?);?(?:\s+Fax:\s*(?<PickupFax>[\+\-0-9 \(\)]+?))?(?:\s+(?:Weather|Map|Security)\s*\|?)*\s+(?<PickupDatetime>(?:[A-Z]|\d+:\d+)[^\n\+\(\)]+?\d{4})\s#si", $text, $m)) {
            $r->pickup()
                ->location($this->rplSpace($m['PickupLocation']))
                ->phone($m['PickupPhone'])
                ->date(strtotime($m['PickupDatetime']));

            if (!empty($m['PickupFax'])) {
                $r->pickup()
                    ->fax($m['PickupFax']);
            }
        }

        if (empty($r->getPickUpDateTime())) {
            if (preg_match("/{$this->opt($this->t('Pick Up:'))}\n(?<pickDate>[\d\:]+\sA?P?M\,\s*\w+\,\s*\w+\s\d{1,2})\n(?<pickLoc>.+)\s{$this->opt($this->t('Tel:'))}/", $text, $m)) {
                $r->pickup()
                    ->date($this->normalizeDate($m['pickDate']))
                    ->location($m['pickLoc']);
            }
        }

        if ($company = $this->re("#^\s*[^\n]+?\d{4}\s+(?>Add\s+To\s+Calendar\s+)?[\-]*\s*([^\n]+?)\s*?\n#", $text)) {
            $r->setCompany($company);
        }

        if ($type = $this->re("#Type:\s*([^\n]+?)\s*?\n#", $text)) {
            $r->setCarType($type);
        }

        if (!empty($this->passengers)) {
            $r->general()
                ->travellers($this->passengers);
        }

        if ($status = $this->re("#Status:\s*([^\n]+?)\s*?\n#", $text)) {
            $r->setStatus($status);
        }

        if ($account = $this->re("#{$this->opt($this->t('Renter ID:'))}\s*([^\n]+?)\s*?\n#", $text)) {
            $r->ota()->account($account, true);
        }

        if (preg_match("#Total:\s*([A-Z]{3})\s*([\d.,]+)\s+#", $text, $m)) {
            $r->price()
                ->total($this->amount($m[2]))
                ->currency($this->currency($m[1]));
        }
    }

    private function parseHotel(Email $email, ?\DOMNode $root = null, ?string $plain = null)
    {
        $text = '';

        if (!empty($root)) {
            $text = implode("\n", $this->http->FindNodes("descendant::text()[normalize-space(.)]", $root));
        }

        if (empty($text) && !empty($plain)) {
            $text = $plain;
        }

        $h = $email->add()->hotel();

        $this->year = $this->re('/hotel\s+\-\s+\w+\s+\w+\s+\d+\s+(\d+)/i', $text);

        if ($conf = trim(str_ireplace(['SMKING CONF'], [''], $this->re("#Confirmation:\s*([^\n]+?)\s*?\n#", $text)))) {
            $h->general()
                ->confirmation($conf);
        }

        if ($hotel = $this->re("#^\s*.+?\d{4}[\s,<>]*\s+(?:Add\s+To\s+Calendar\s+)?[\-]*\s*(.+?)\s*?(?:\n|Address)#is", $text)) {
            $h->hotel()
                ->name($hotel);
        }

        if (preg_match("#Check\s+In\/Check\s+Out:\s*([^\n]+?\d{4}(?:\s*\d{1,2}\:\d{2}[ ]+[AP]M)?)\s*\-\s*([^\n]+?\d{4}(?:\s*\d{1,2}\:\d{2}[ ]+[AP]M)?)#", $text, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]));
        }

        if (preg_match("#Check\s+In:\s*\n(\w+\,\s\w+\s\d+)\nCheck\s+Out:\n(\w+\,\s\w+\s\d+)#", $text, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]));
        }

        if ($addr = $this->re("#Address\s*:\s*(.+?)(?:\s+(?:Weather|Map|Security)\s*\|?)*\s+Tel:\s#si", $text)) {
            $h->hotel()
                ->address($this->rplSpace($addr));
        }

        if (empty($h->getAddress())) {
            $addr = $this->re("#{$this->opt($this->t('Address'))}\:\s+(.+)\s+{$this->opt($this->t('Tel:'))}#s", $text);
            $h->hotel()
                ->address(trim(str_replace("\n", "", $addr)));
        }

        if ($phone = $this->re("#\s{$this->opt($this->t('Tel:'))}\s*([^\n]+?)\s*?\n#", $text)) {
            $h->hotel()
                ->phone($phone);
        }

        if ($fax = $this->re("#\sFax:\s*([^\n]+?)\s*?\n#", $text)) {
            $h->hotel()
                ->fax($fax);
        }

        if (!empty($this->passengers)) {
            $h->general()
                ->travellers($this->passengers);
        }

        if ($guests = $this->re("#Number\s+of\s+Persons:\s*(\d+)\s#", $text)) {
            $h->booked()
                ->guests($guests);
        }

        if ($rooms = $this->re("#{$this->opt($this->t('No. of Rooms:'))}\s*(\d+)\s#", $text)) {
            $h->booked()
                ->rooms($rooms);
        }

        $r = $h->addRoom();

        if ($rate = $this->re("#{$this->opt($this->t('Rate per night:'))}\s*([^\n]+?)\s*?\n#", $text)) {
            $r->setRate($rate);
        }

        if ($cancel = trim($this->re("#{$this->opt($this->t('Cancellation Policy:'))}\s*([^\n]+)#", $text))) {
            $h->setCancellation(preg_replace('/\-{2,}/', '', $cancel));
        }

        if (!empty($h->getCancellation())) {
            if (preg_match('/CXL (\d{1,2} DAYS) PRIOR TO ARRIVAL/i', $h->getCancellation(), $m)) {
                $h->booked()
                    ->deadlineRelative($m[1]);
            } elseif (preg_match('/CANCEL (\d{1,2} HOURS) PRIOR TO ARRIVAL/i', $h->getCancellation(), $m)) {
                $h->booked()
                    ->deadlineRelative($m[1]);
            } elseif (preg_match('/(\d{1,2} day) prior to arrival/i', $h->getCancellation(), $m)) {
                $h->booked()
                    ->deadlineRelative($m[1]);
            }
        }

        if ($type = $this->re("#Room\s+Type:\s*([^\n]+?)\s*?\n#", $text)) {
            $r->setType($type);
        }

        if ($description = $this->re("#{$this->opt($this->t('Room Description:'))}\s*([^\n]+?)\s*?\n#", $text)) {
            $r->setDescription($description);
        }

        if ($status = $this->re("#Status:\s*(\w+)\s*?\n#", $text)) {
            $h->setStatus($status);
        }
    }

    private function parseAir(Email $email, ?\DOMNode $root = null, ?string $plain = null)
    {
        $text = '';

        if (!empty($root)) {
            $text = implode("\n", $this->http->FindNodes("descendant::text()[normalize-space(.)]", $root));
        }

        if (empty($text) && !empty($plain)) {
            $text = $plain;
        }

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'flight') {
                $f = $it;
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();

            $f->general()
                ->noConfirmation();

            if (!empty($this->passengers)) {
                $f->general()
                    ->travellers($this->passengers);
            }

            if ($tNums = $this->http->FindNodes("//td[{$this->eq('Ticket Number:')}][not(.//td)]/following-sibling::td[1]", null, '/^[ ]*(\d+)[ ]+/')) {
                $f->issued()
                    ->tickets($tNums, false);
            } elseif ($tNums = $this->http->FindNodes("//td[{$this->starts('New Ticket /')}][not(.//td)]", null, '/\/\s*((?:\d{3} )?\d+)\s*$/')) {
                $f->issued()
                    ->tickets($tNums, false);
            }
        }

        $this->year = $this->re("/^AIR\s*\-\s*\w+, \s*\w+\s*\d+\s*(\d{4})$/", $text);

        if (preg_match("#{$this->opt($this->t('FF Number:'))}\s*(\w+[\w ]*?) - (.+)\s*\n#", $text, $m)
            && !in_array($m[1], array_column($f->obtainTravelAgency()->getAccountNumbers(), 0))
        ) {
            $f->ota()->account($m[1], false, $this->niceTravellers($m[2]));
        }

        $s = $f->addSegment();

        if (preg_match("#\n\s*(?<AirlineName>[^\n]+?)\s+Flight\s+(?<AirlineCode>[A-Z\d][A-Z]|[A-Z][A-Z\d])?(?<FlightNumber>\d+)\s+(?<Cabin>[\w\- ]+?)\s+Class#i", $this->re("#((?>[^\n]+\s+){4})#", $text), $m)) {
            $s->airline()
                ->name(!empty($m['AirlineCode']) ? $m['AirlineCode'] : $m['AirlineName'])
                ->number($m['FlightNumber']);
            $s->extra()
                ->cabin($m['Cabin']);
        }

        if (preg_match("#{$this->opt($this->t('Record Locator:'))}\s*([A-Z\d]{5,7})(?:\s+|\))#ui", $text, $m)
            && $m[1] !== $this->otaNumber
        ) {
            $s->airline()
                ->confirmation($m[1]);
        }

        if (preg_match("#{$this->opt($this->t('Operated By'))}\s*(\/)?(.+?)\s*(?: DBA |\*|\(|\n)#ui", $text, $m)) {
            $s->airline()
                ->operator($m[2]);

            if (!empty($m[1])) {
                $s->airline()
                    ->wetlease();
            }
        }

        $departText = $this->re("/\n\s*Depart:\s*([\s\S]+?)\n\s*Arrive:/", $text);
        $departText = preg_replace("/(\n\w+(\s*\|\s*\w+){2,}\n)/", "\n", $departText);
        $depName = $depDate = '';

        if (
            preg_match("/^\s*(?<date>\d{1,2}:\d{2}.*?,\s*\w+[\s,]+\w+[\s,]+\w+.*)\n\s*(?<name>[\s\S]+)/", $departText, $m)
            || preg_match("/^\s*(?<name>[\s\S]+?)\n\s*(?<date>\d{1,2}:\d{2}[\s\S]+)/", $departText, $m)
        ) {
            $depName = $m['name'];
            $depDate = $m['date'];
        }

        $arriveText = $this->re("/\n\s*Arrive:\s*([\s\S]+?)\n\s*[[:alpha:]]+:/", $text);
        $arriveText = preg_replace("/(\n\w+(\s*\|\s*\w+){2,}\n)/", "\n", $arriveText);
        $arrName = $arrDate = '';

        if (
            preg_match("/^\s*(?<date>\d{1,2}:\d{2}.*?,\s*\w+[\s,]+\w+[\s,]+\w+.*)\n\s*(?<name>[\s\S]+)/", $arriveText, $m)
            || preg_match("/^\s*(?<name>[\s\S]+?)\n\s*(?<date>\d{1,2}:\d{2}[\s\S]+)/", $arriveText, $m)
        ) {
            $arrName = $m['name'];
            $arrDate = $m['date'];
        }

        $terminalRe = "(?:,|\n)\s*(?:\w[\w ]*? )?Terminal(?: \w[\w ]*?)?";
        $re1 = "#^\s*(?<code>[A-Z]{3})\s+\-\s+(?<name>[^\n]+?)\s*(?<terminal>{$terminalRe})?\s*(?<name2>\n\s*\S[\s\S]*)?\s*$#i";
        $re2 = "#^\s*(?<name>[^\n]+?)\s*\((?<code>[A-Z]{3})\)\s*(?<terminal>{$terminalRe})?\s*(?<name2>\n\s*\S[\s\S]*)?\s*$#i";
        $re3 = "#^\s*(?<name>[^\n]+?)\s*(?<terminal>{$terminalRe})?\s*(?<name2>\n\s*\S[\s\S]*)?\s*$#i";
        // $this->logger->debug('$re1 = '.print_r( $re1,true));
        // $this->logger->debug('$re2 = '.print_r( $re2,true));
        // $this->logger->debug('$re3 = '.print_r( $re3,true));

        if (preg_match($re1, $depName, $m)
            || preg_match($re2, $depName, $m)
            || preg_match($re3, $depName, $m)
        ) {
            if (!empty($m['code'])) {
                $s->departure()
                    ->code($m['code']);
            } else {
                $s->departure()
                    ->noCode();
            }
            $s->departure()
                ->name(implode(', ', [trim($m['name']), trim($m['name2'])]));

            if (!empty($m['terminal'])) {
                $s->departure()
                    ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", ' ', trim($m['terminal'], ','))));
            }
        }

        $s->departure()
            ->date($this->normalizeDate($depDate));

        if (preg_match($re1, $arrName, $m)
            || preg_match($re2, $arrName, $m)
            || preg_match($re3, $arrName, $m)
        ) {
            if (!empty($m['code'])) {
                $s->arrival()
                    ->code($m['code']);
            } else {
                $s->arrival()
                    ->noCode();
            }
            $s->arrival()
                ->name(implode(', ', [trim($m['name']), trim($m['name2'])]));

            if (!empty($m['terminal'])) {
                $s->arrival()
                    ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", ' ', trim($m['terminal'], ','))));
            }
        }

        $s->arrival()
            ->date($this->normalizeDate($arrDate));

        if ($aircraft = $this->re("#Equipment\s*:\s*([^\n]+?)\s*?(?:\n|Seat)#", $text)) {
            $s->extra()
                ->aircraft($aircraft);
        }

        $seatText = $this->re("#\n\s*Seat\s*:\s*([\S\s]+?)\n[[:alpha:]]+:#", $text);

        if (preg_match_all("/^\s*(\d{1,3}[A-Z]) [[:alpha:]]+ - (.+)/m", $seatText, $m)) {
            foreach ($m[0] as $i => $v) {
                $s->extra()
                    ->seat($m[1][$i], true, true, $this->niceTravellers($m[2][$i]));
            }
        } elseif (preg_match("#\n\s*Seat\s*:\s*(\d+[A-Z](?:\s*,\s*\d+[A-Z])*)\s+#", $text, $m)) {
            $s->extra()
                ->seats(preg_split("/\s*,\s*/", $m[1]));
        }

        if ($duration = $this->re("#Duration\s*:\s*(\d+[^\n]+?)(?:\s+Non\-stop|\n)#i", $text)) {
            $s->extra()
                ->duration($duration);
        }

        if ($meal = $this->re("#Meal\s*:\s*([^\n]+?)\s*?\n#", $text)) {
            $s->extra()
                ->meal($meal);
        }

        if ($bookingCode = $this->re("#{$this->opt($this->t('Booking Code:'))}\s*([^\n]+?)\s*?\n#", $text)) {
            $s->extra()
                ->bookingCode($bookingCode);
        }

        if (preg_match("#Status:\s*(\w+)\s*?\n#", $text, $m)) {
            $s->extra()
                ->status($m[1]);
        }

        if (preg_match("#\n\s*Distance:\s*([\w ]+)\s*\n#", $text, $m)) {
            $s->extra()
                ->miles($m[1]);
        }

        if (preg_match("#\n\s*Stops:\s*(Non-stop)\s*\n#i", $text, $m)) {
            $s->extra()
                ->stops(0);
        }
    }

    private function parseRail(Email $email, ?\DOMNode $root = null, ?string $plain = null)
    {
        $text = '';

        if (!empty($root)) {
            $text = implode("\n", $this->http->FindNodes("descendant::text()[normalize-space(.)]", $root));
        }

        if (empty($text) && !empty($plain)) {
            $text = $plain;
        }

        $t = $email->add()->train();

        if ($conf = $this->re("#Confirmation\s*:\s*(\w+)\s*#ui", $text)) {
            $t->general()
                ->confirmation($conf);
        }

        if (!empty($this->passengers)) {
            $t->general()
                ->travellers($this->passengers);
        }

        // if (!empty($this->ticketNumbers)) {
        //     foreach ($this->ticketNumbers as $ticketNumber) {
        //         $t->addTicketNumber($ticketNumber, false);
        //     }
        // }

        if ($tax = $this->re("#Service\s+Fee\s*:[^\n]*?\s+(?:\w+?|\W)\s*([\d.,]+)\s*?\n#", $text)) {
            $t->price()
                ->tax($this->amount($tax));
        }

        $s = $t->addSegment();

        if (preg_match("#\n\s*(?<TrainCompany>[^\n]+?)\s+Train Number\s+(?<TrainNumber>\d+)\s+#i", $this->re("#((?>[^\n]+\s+){4})#", $text), $m)) {
            $s->extra()
                ->number($m['TrainNumber']);
        }

        if ($dep = $this->re("#Depart:\s*(.+)\n#i", $text)) {
            $s->departure()
                ->name($dep);
        }

        $re = "#Depart\s*:\s*.+?(?<Time>\d+:\d+\s*(?:PM|AM))\s+\w+,\s+(?<Month>\w+)\s+(?<Day>\d+)[\,]*\s+(?<Year>\d{4})\s*#s";

        if (preg_match($re, $text, $m)) {
            $s->departure()
                ->date(strtotime($m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'] . ' ' . $m['Time']));
        }

        if ($arr = $this->re("#Arrive:\s*(.+)\n#i", $text)) {
            $s->arrival()
                ->name($arr);
        }

        $re = "#Arrive\s*:\s*.+?(?<Time>\d+:\d+\s*(?:PM|AM))\s+\w+,\s+(?<Month>\w+)\s+(?<Day>\d+)[\,]*\s+(?<Year>\d{4})\s*#s";

        if (preg_match($re, $text, $m)) {
            $s->arrival()
                ->date(strtotime($m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'] . ' ' . $m['Time']));
        }

        $seat = $this->re("#Seat\s*:\s*(\d+\w(?:\s*,\s*\d+\w)*)#", $text);

        if (($seats = explode(',', $seat)) && 1 < count($seats)) {
            $s->extra()
                ->seats($seats);
        } elseif (!empty($seat)) {
            $s->extra()
                ->seat($seat);
        }

        if ($duration = $this->re("#Duration\s*:\s*(\d+[^\n]+?)(?:\s+Non\-stop|\n)#i", $text)) {
            $s->extra()
                ->duration($duration);
        }

        if ($meal = $this->re("#Meal\s*:\s*([^\n]+?)\s*?\n#", $text)) {
            $s->extra()
                ->meal($meal);
        }

        if ($cabin = $this->re("#Class Of Service\s*:\s*([^\n]+?)\s*?\n#", $text)) {
            $s->extra()
                ->cabin($cabin);
        }
    }

    private function re(string $re, string $text): ?string
    {
        if (preg_match($re, $text, $m)) {
            return $m[1];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace(',', '.', $price);
        $price = str_replace(' ', '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug("Date: {$str}");
        $year = $this->year;
        $in = [
            // 01:53 PM Monday, March 16, 2015
            "/^\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s+[[:alpha:]]+\,\s*([[:alpha:]]+)\s+(\d{1,2})\s*,?\s*(\d{4})\s*$/iu",

            "/^(\w+)\,\s(\w+)\s(\d+)$/", //Friday, May 22,
            "/^([\d\:]+\sA?P?M)\,\s*(\w+)\,\s*(\w+)\s(\d{1,2})$/", //2:00 PM, Saturday, May 23
        ];
        $out = [
            "$3 $2 $4, $1",
            "$1, $3 $2 $year",
            "$2, $4 $3 $year, $1",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug("Date: {$str}");

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function rplSpace(string $s)
    {
        return preg_replace('/\s+/', ' ', $s);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }
}
