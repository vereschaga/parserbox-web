<?php

namespace AwardWallet\Engine\pleasant\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ProtectionPlan extends \TAccountChecker
{
    public $mailFiles = "pleasant/it-17026614.eml, pleasant/it-17680765.eml, pleasant/it-18819815.eml, pleasant/it-19064612.eml, pleasant/it-22495652.eml, pleasant/it-22548779.eml, pleasant/it-22595203.eml, pleasant/it-32938688.eml";

    public $reBody2 = [
        "en" => ["Protection Plan", "Protection Waiver Plan", "Cancel For Any Reason-"],
    ];
    public static $dictionary = [
        "en" => [
            "Protection Plan" => ["Travel Protection Plan", "Travel Protection Waiver Plan", "Protection Plan", 'Cancel For Any Reason-'],
            "Conf#"           => ["Conf#", "Confirmation"],
        ],
    ];

    public $lang = "en";

    private static $headers = [
        'pleasant' => [
            'from' => ['pleasant.net', 'pleasantholidays.com'],
            'subj' => [
                'Pleasant Holidays: Itinerary',
            ],
        ],
        'journese' => [
            'from' => ['journese.com'],
            'subj' => [
                'Journese Itinerary/',
                'Journese: Itinerary',
            ],
        ],
    ];
    private $pdfPattern = ".*\.pdf";

    private $code;
    private $bodies = [
        'pleasant' => [
            'pleasantholidays.com',
            'Pleasant Holidays',
        ],
        'journese' => [
            'Journese.com',
            'Journese Res #',
        ],
    ];
    private $text;

    private $patterns = [
        'time'  => '\b\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'time2' => '\b\d{1,2}:\d{2} ?[AP]\b', // 11:40P
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom || $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }
        }

        $code = $this->getProvider($parser, $text);

        if (empty($code)) {
            return false;
        }

        foreach ($this->reBody2 as $reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($text, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return $email;
            }

            foreach ($this->reBody2 as $lang => $reBody2) {
                foreach ($reBody2 as $re) {
                    if (strpos($this->text, $re) !== false) {
                        $this->lang = substr($lang, 0, 2);

                        if (!$this->parsePdf($email)) {
                            $this->logger->info("parsePdf is failed'");

                            return $email;
                        }
                        $email->setProviderCode($this->getProvider($parser, $this->text));

                        break;
                    }
                }
            }
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    /*private function split($re, $text, $shiftFirst = true)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];
        if (count($r) > 1){
            if ($shiftFirst == true || ($shiftFirst == false && empty($r[0]))) {
                array_shift($r);
            }
            for($i=0; $i<count($r)-1; $i+=2){
                $ret[] = $r[$i]."====".$r[$i+1];
            }
        } elseif (count($r) == 1){
            $ret[] = reset($r);
        }
        return $ret;
    }*/

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function getProvider(PlancakeEmailParser $parser, $text)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($text, $search) !== false) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parsePdf(Email $email): bool
    {
        $text = $this->text;

        // removing garbage fragments
        $text = preg_replace_callback("/(?:^.+(?:\n(?!\n).*)*)?^.*{$this->opt(['COVID-19'])}.*\n?(?:^.+(?:\n(?!\n).*)*)?/im", function ($m) {
            return preg_match("/^[ ]{11,}\S/m", $m[0]) > 0 ? $m[0] : '';
        }, $text);
        $text = preg_replace('/\n.*?Res[ ]?#.*Page[ ]*\.+[ ]*\d+[ ]*\n/', "\n", $text);

        // Travel Agency
        if (!empty($trip = $this->re("/Res[ ]?#[ ]*([A-Z\d]{5,})\b/", $text))) {
            $email->ota()->confirmation($trip);
        }

        $total = $this->re("#" . $this->opt($this->t("Total Reservation")) . "\s+(\S\s*[\d,.]+|[A-Z]{3}\s+[\d,.]+|[\d,.]+\s+[A-Z]{3})#", $this->text);

        if (!empty($total)) {
            $email->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }

        if (is_array($this->t("Protection Plan"))) {
            foreach ($this->t("Protection Plan") as $value) {
                $findResult = strpos($text, $value);

                if ($findResult === false) {
                    continue;
                }

                if (!isset($planBegin) || $planBegin === false || $planBegin > $findResult) {
                    $planBegin = $findResult;
                }
            }
        } else {
            $planBegin = strpos($text, 'Protection Plan');
        }

        if ($planBegin !== false && 2000 < $planBegin) {
            $planBegin = strpos($text, 'Cancel For Any Reason-CashBack Purchased');

            if (empty($planBegin)) {
                $planBegin = strpos($text, 'Cancel For Any Reason-FTC Purchased');
            }

            if (empty($planBegin)) {
                $planBegin = strpos($text, 'Pleasant Protection Plan not Purchased');
            }

            if (empty($planBegin)) {
                $planBegin = strpos($text, 'Protection Plan not purchased');
            }
        }

        if (empty($planBegin)) {
            $this->logger->info("can't finded 'Protection Plan'");

            return false;
        }

        // General
        $headerText = substr($text, 0, $planBegin);
        $planEnd = strpos($text, 'Total Reservation');

        if ($planEnd !== false) {
            $planText = preg_replace("#^.*#", "\n", substr($text, $planBegin, $planEnd - $planBegin));
            $footerText = preg_replace("#^.*#", "\n", substr($text, $planEnd));
        } else {
            $planText = preg_replace("#^.*#", "\n", substr($text, $planBegin));
            $footerText = null;
        }

        $generalText = $headerText . "\n\n" . $footerText;

        $general['Status'] = $this->re("#Reservation Status[ ]*:[ ]*(.+)#", $generalText);
        $general['ReservationDate'] = $this->normalizeDate($this->re("#Reservation Date[ ]*:[ ]*(.+)#", $generalText));

        $passengersText = $this->re("#Reserved For :.+\n((?:[ ]*\d+\.[ ]*.*\n)+)#", $generalText);

        if (!empty($passengersText)) {
            $passengers = explode("\n", $passengersText);

            if (!empty($passengersText)) {
                preg_match_all("#\b(\d+)[ ]*\.[ ]*(\S.+?)(?:\([^)]*?\))?(?:\s{2,}|\n)#", $passengersText, $passengerMatches);

                foreach ($passengerMatches[0] as $key => $value) {
                    $general['Passengers'][$passengerMatches[1][$key]] = $passengerMatches[2][$key];
                }
            }
        } else {
            $passengersText = $this->re("#Passenger list[ ]*:[ ]*\n+((?:[ ]*\d+\.[ ]*.*\n)+)#", $planText);

            if (!empty($passengersText)) {
                preg_match_all("#\b(\d+)[ ]*\.[ ]*(\S.+?)(?:\([^)]*?\))?(?:\s{2,}|\n)#", $passengersText, $passengerMatches);

                foreach ($passengerMatches[0] as $key => $value) {
                    $general['Passengers'][$passengerMatches[1][$key]] = $passengerMatches[2][$key];
                }
            }
        }

        $days = $this->splitter("#\n[ ]{0,10}(\d{1,2}\-\w+\-\d{4}[ ]+)#u", $planText);

        $segments = [];

        foreach ($days as $key => $dayText) {
            $date = $this->normalizeDate($this->re("#^\s*(\d{1,2}\-\w+\-\d{4})#", $dayText));

            if (empty($date)) {
                $this->logger->alert('Date was not found for segment');

                return false;
            }
            $dayText = preg_replace('#^\s*[ ]*\d{1,2}-\w+-\d{4}[ ]+(.+\n)\w+#', "\n$1", $dayText);
            $segmentsL = $this->splitter("#(?:\n\s*|^\s*)(.*?(?:\d+[ ]*Transfers\s*\n|on .* Flight \d+|on .* Ship \d+|Flights not Assigned|(?:(?:[ ]*\S.+\n){0,2}?.*for \d+ Nights? for \d+ Guests?|(?:\n.*)?Pick up in|Return .* Car|Return .* in |.* for \d+ Guests? in )))#u", $dayText);
            $segments = array_merge($segments, array_map(function ($v) use ($date) {return ["date" => $date, "text" => $v]; }, $segmentsL));
        }

        $flights = [];
        $hotels = [];
        $cars = [];
        $cruise = [];
        $events = [];
        $transfers = [];
        $substr = 58;

        foreach ($segments as $i => $stext) {
            switch (true) {
                case $this->re("#^\s*Own Arrangements.*\n\s*(for \d+ Nights? for \d+ Guests?)#i", $stext['text']):
                    $this->logger->debug("segment-$i: unknown hotel");

                    break;

                case $this->re("#(Departing|Arriving).+airport\sat#", $stext['text']):
                case $this->re("#(on .* Flight \d+)[\s\S]*?(\n\s*Departing|\n\s*Arriving )#", $stext['text']):
                    $this->logger->debug("segment-$i: FLIGHT -> " . substr($stext['text'], 0, $substr));
                    $flights[] = $stext;

                    break;

                case $this->re("#(for details on American Cruise Lines)#", $stext['text']):
                    $this->logger->debug("segment-$i: CRUISE -> " . substr($stext['text'], 0, $substr));
                    $cruise[] = $stext;

                    break;

                case $this->re("#(\D+Cruise\s*\d+)#", $stext['text']):
                    $this->logger->debug("segment-$i: CRUISE -> " . substr($stext['text'], 0, $substr));
                    $cruise[] = $stext;

                    break;

                case $this->re("#(on .* Ship \d+)#", $stext['text']):
                    $this->logger->debug("segment-$i: CRUISE -> " . substr($stext['text'], 0, $substr));
                    $cruise[] = $stext;

                    break;

                case $this->re("#(for \d+ Nights? for \d+ Guests?)#", $stext['text']):
                    $this->logger->debug("segment-$i: HOTEL -> " . substr($stext['text'], 0, $substr));
                    $hotels[] = $stext;

                    break;

                case $this->re("#(Pick up in|(?:\n|^)Return .*? in .*?(\n.*)? to)#", $stext['text']):
                    $this->logger->debug("segment-$i: CAR -> " . substr($stext['text'], 0, $substr));
                    $cars[] = $stext;

                    break;

                case $this->re("#(.*\bDuration:.*)#i", $stext['text']) && $this->re("/(for \d{1,3} Guest.+?\s+at\s+\d+:\d+)/i", $stext['text']):
                    $this->logger->debug("segment-$i: EVENT -> " . substr($stext['text'], 0, $substr));
                    $events[] = $stext;

                    break;

                case $this->re("#^\s*(\d+[ ]*Transfer[s]?[ ]*\n)#", $stext['text']):
                    $this->logger->debug("segment-$i: TRANSFER -> " . substr($stext['text'], 0, $substr));
                    $transfers[] = $stext;

                    break;

                case $this->re("#^\s*(.*Boat Trans.*)#", $stext['text']):
                    $this->logger->debug("segment-$i: TRANSFER -> " . substr($stext['text'], 0, $substr));

                    break;

                case $this->re("#^\s*(Flights not Assigned)#", $stext['text']):
                    $this->logger->debug("segment-$i: FLIGHT (not assigned) -> " . substr($stext['text'], 0, $substr));

                    break;

                case $this->re("#(for \d+ Guest.+?\s+in\s+.*\s+)#i", $stext['text']) && empty($this->re("/(for \d{1,3} Guest.+?\s+in\s+.*\s+at\s+\d+:\d+)/i", $stext['text'])):
                    $this->logger->debug("segment-$i: guest type -> " . substr($stext['text'], 0, $substr));

                    break;

                case $this->re("#^\s*(.+)\s*$#", $stext['text']):
                    $this->logger->debug("segment-$i: blank type -> " . substr($stext['text'], 0, 55));

                    break;

                default:
                    $this->logger->debug("segment-$i: unknown type\n" . $stext['text']);

                    return false;

                    break;
            }
        }

        //#################
        //##   FLIGHT   ###
        //#################

        $airs = [];

        foreach ($flights as $stext) {
            $rl = implode(",", $this->res("# " . $this->opt($this->t("PNR")) . " ([A-Z\d]{5,7})\b#", $stext['text']));

            if (empty($rl)) {
                $rl = 'UNKNOWN';
                $this->logger->debug('RL not matched');
            }
            $airs[$rl][] = $stext;
        }

        foreach ($airs as $rl => $fsegments) {
            $f = $email->add()->flight();
            $guestNumbers = [];

            // General
            if ($rl == 'UNKNOWN') {
                $f->general()->noConfirmation();
            } else {
                $rls = explode(",", $rl);

                foreach ($rls as $value) {
                    $f->general()
                        ->confirmation($value, "PNR");
                }
            }
            $f->general()
                ->status($general['Status'] ?? null)
                ->date($general['ReservationDate'] ?? null);

            $tickets = [];

            foreach ($fsegments as $key => $segText) {
                $stext = $segText['text'];

                if (preg_match("#on .+ Flight \d+\s+Arriving #", $stext)) {
                    continue;
                }

                if (preg_match_all("#\n\s*Passengers? ([\d,\- ]+)(?=[ ]+PNR|\n|$)#", $stext, $guestsMatches)) {
                    foreach ($guestsMatches[1] as $value) {
                        $guestNumbers = array_merge($guestNumbers, $this->getGuestsNumbers($value));
                    }
                }

                $s = $f->addSegment();

                // Airline
                if (preg_match("#on (.+) Flight (\d+)#", $stext, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                if (preg_match("#Operated by (.+)#", $stext, $m)) {
                    $s->airline()
                        ->operator(trim($this->re("#(.+?)(?: AS |$)#", $m[1])));
                }

                // Departure
                if (preg_match("#\n\s*Departing (.+) at (.+)#", $stext, $m)) {
                    $s->departure()
                        ->noCode()
                        ->name($m[1])
                        ->date(strtotime($this->normalizeTime($m[2]), $segText['date']));
                }

                // Arrival
                if (preg_match("#\n\s*Arriving (.+) at (.+)#", $stext, $m)) {
                    $s->arrival()
                        ->noCode()
                        ->name($m[1])
                        ->date(strtotime($this->normalizeTime($m[2]), $segText['date']));
                } elseif (!preg_match("#\n\s*Arriving #", $stext) && !empty($fsegments[$key + 1]) && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
                    if (preg_match("#" . $s->getAirlineName() . "\s+Flight\s+" . $s->getFlightNumber() . "\s+Arriving (.+) at (.+)#", $fsegments[$key + 1]['text'], $m)) {
                        $s->arrival()
                            ->noCode()
                            ->name($m[1])
                            ->date(strtotime($this->normalizeTime($m[2]), $fsegments[$key + 1]['date']));
                    }
                }

                // Extra
                if (preg_match_all("#Seats? (.+)#", $stext, $seatsMatches)) {
                    $seats = [];

                    foreach ($seatsMatches[1] as $value) {
                        $seats = array_merge($seats,
                                array_filter(array_map(function ($v) {
                                    if (preg_match("#^\s*(\d{1,3}[A-Z])\b#", $v, $m)) {
                                        return $m[1];
                                    }

                                    return null;
                                }, explode(",", $value))));
                    }

                    if (!empty($seats)) {
                        $s->extra()->seats($seats);
                    }
                }

                if (preg_match("#in (.+?) on (.+) Flight \d+#", $stext, $m)) {
                    $s->extra()->cabin($m[1]);
                }

                // Issued
                if (preg_match_all("#Ticket\# (\d{10,})#", $stext, $m)) {
                    $tickets = array_merge($tickets, $m[1]);
                }
            }

            if (!empty($guestNumbers) && !empty($general['Passengers'])) {
                $guestNumbers = array_unique($guestNumbers);
                sort($guestNumbers);

                foreach ($guestNumbers as $key => $value) {
                    if (isset($general['Passengers'][$value])) {
                        $f->general()
                            ->traveller($general['Passengers'][$value], true);
                    }
                }
            }

            $tickets = array_values(array_unique(array_filter($tickets)));

            if (!empty($tickets)) {
                $f->issued()->tickets($tickets, false);
            }
        }

        //################
        //##   HOTEL   ###
        //################
        foreach ($hotels as $segText) {
            $htext = $segText['text'];
            $htext = preg_replace("#^\s*Transfer\s*\n#", '', $htext);
            $guestNumbers = [];

            $h = $email->add()->hotel();
            $r = $h->addRoom();

            // General
            $findConf = false;
            $conf = $this->res("# " . $this->opt($this->t("Confirmation")) . ".*? ([A-z\d\-]{4,}(?:-[A-Z\d]*)?)(?: .*|\n)#", $htext);

            foreach ($conf as $value) {
                $h->general()->confirmation($value, "Confirmation");
                $findConf = true;
            }

            if ($findConf === false
                && preg_match("#({$this->opt($this->t("Confirmation"))}) (?:CFD [\d.]+|SS)#", $htext)
            ) {
                $h->general()->noConfirmation();
            }

            if ($findConf == false && !preg_match("#(" . $this->opt($this->t("Confirmation")) . ")#", $htext)) {
                $h->general()->noConfirmation();
            }

            $h->general()
                ->status($general['Status'] ?? null)
                ->date($general['ReservationDate'] ?? null);

            if (preg_match_all("#\n\s*Guests? ([\d,\- ]+)(?=[ ]{3,}|\n)#", $htext, $guestsMatches)) {
                foreach ($guestsMatches[1] as $value) {
                    $guestNumbers = array_merge($guestNumbers, $this->getGuestsNumbers($value));
                }
            }

            if (!empty($guestNumbers) && !empty($general['Passengers'])) {
                $guestNumbers = array_unique($guestNumbers);
                sort($guestNumbers);

                foreach ($guestNumbers as $key => $value) {
                    if (isset($general['Passengers'][$value])) {
                        $h->general()
                            ->traveller($general['Passengers'][$value], true);
                    }
                }
            }

            if (preg_match("/^\s*(?<name>.{2,})\s+(?<rooms>\d{1,3})[ ]+(?<type>[\s\S]+?)\s+for (?<days>\d{1,3}) Nights? for (?<guest>\d{1,3}) Guests?(?:\n.*){0,3}\n[ ]*Guests?\b.*(?:\n.+)*\n\n/", $htext, $m)) {
                $h->hotel()->name($m['name']);
                $hotelType = $m['type'];
                $h->booked()
                    ->checkIn($segText['date'])
                    ->checkOut(strtotime('+' . $m['days'] . 'day', $segText['date']))
                    ->guests($m['guest'])
                    ->rooms($m['rooms']);
            }

            $endAddress = ['Room Number ', 'Breakfast Plan', 'All-Inclusive Plan', 'September Peak Sale', 'Check in:'];

            if (preg_match("/\bfor \d{1,3} Nights? for \d{1,3} Guests?(?:\n.*){0,3}\n[ ]*Guests?\b.*(?:\n.+)*\n\n([\s\S]{3,}?)\s+{$this->opt($endAddress, true)}/", $htext, $m) // it-17026614.eml
                || preg_match("/\bfor \d{1,3} Nights? for \d{1,3} Guests?(?:\n.*){0,3}\n[ ]*Guests?\b.*(?:\n.+)*\n\n((\s*\S+.*\n){1,5}?[ ]*{$this->patterns['phone']})\n/", $htext, $m) // it-17680765.eml
                || preg_match("/\bfor \d{1,3} Nights? for \d{1,3} Guests?(?:\n.*){0,3}\n[ ]*Guests?\b.*(?:\n.+)*\n\n((\s*\S+.*\n){1,5}?)\n/", $htext, $m)
            ) {
                if (preg_match("/^\s*(?<address>[\s\S]{3,}?)\n+[ ]*(?<phone>{$this->patterns['phone']})[ .]*$/", $m[1], $mat)) {
                    $h->hotel()->address(preg_replace('/\s+/', ' ', $mat['address']))->phone($mat['phone']);
                } else {
                    $h->hotel()->address(preg_replace("#\s+#", ' ', trim($m[1])));
                }
            }

            if (isset($hotelType)) {
                if (preg_match("/^\s*(.+\S)\s+-\s+(.*(?:Rate|Stay|Night Free|Promotion|Offer|Experience|Package|Exclusive).*?)\s*$/s", $hotelType, $m)) {
                    $r->setType(preg_replace("/\s+/", ' ', $m[1]))->setRateType(preg_replace("/\s+/", ' ', $m[2]));
                } elseif (preg_match("/^\s*(.+)\n+\s*(.*(?:Rate|Stay|Night Free|Promotion|Offer|Experience|Package|Exclusive).*?)\s*$/", $hotelType, $m)) {
                    $r->setType(preg_replace("/\s+/", ' ', $m[1]))->setRateType(preg_replace("/\s+/", ' ', $m[2]));
                } else {
                    $r->setType(preg_replace("/\s+/", ' ', $hotelType));
                }
                unset($hotelType);
            }

            if (!empty($h->getCheckOutDate()) && preg_match("/\s+CHECK[- ]IN(?: TIME)?[ ]*:[ ]*({$this->patterns['time']}|{$this->patterns['time2']})(?:[ ]{2}|\n|\s*$)/i", $htext, $m)) {
                $h->booked()->checkIn(strtotime($this->normalizeTime($m[1]), $h->getCheckInDate()));
            }

            if (!empty($h->getCheckOutDate()) && preg_match("/\s+CHECK[- ]OUT(?: TIME)?[ ]*:[ ]*({$this->patterns['time']}|{$this->patterns['time2']})(?:[ ]{2}|\n|\s*$)/i", $htext, $m)) {
                $h->booked()->checkOut(strtotime($this->normalizeTime($m[1]), $h->getCheckOutDate()));
            }

            if (preg_match("#\n[ ]*(?:Cancellation Policy:|\n[ ]*CANCELLATION POLICY\n|CANCEL POLICY:|Cancellation Policy for travel during [\d\/\.]+ through [\d\/\.]+\s*)([\s\S]+?)(?:\n.*[:]|\n\n|$)#i", $htext, $m)) {
                if (preg_match("#([\s\S]+?\n[ ]+\S.{0,40})\n((?:.*\n){1,3})#i", $m[1], $mat) && stripos($mat[2], ' cancel') === false) {
                    $h->general()->cancellation(preg_replace('/\s+/', ' ', trim($mat[1])));
                } else {
                    $h->general()->cancellation(preg_replace('/\s+/', ' ', trim($m[1])));
                }
            } elseif (preg_match("#\n[ ]*(.*cancell?ations(?:\s+or\s+changes)? made \d+(?:.*\n)*?(?:.*\.[ ]*\n))#i", $htext, $m)) {
                $h->general()->cancellation(preg_replace('/\s+/', ' ', trim($m[1])));
            }
        }

        //###############
        //##   CARS   ###
        //###############
        foreach ($cars as $key => $segText) {
            $rtext = $segText['text'];

            if (preg_match("#Return .* in#", $rtext) && !preg_match("#Pick up in#", $rtext)) {
                continue;
            }
            $r = $email->add()->rental();

            // General
            $conf = $this->re("# " . $this->opt($this->t("Confirmation")) . ".* ([A-Za-z\d]{5,})\s*\n#", $rtext);

            if (!empty($conf)) {
                $r->general()
                    ->confirmation($conf, "Confirmation");
            } elseif (!preg_match("#(" . $this->opt($this->t("Confirmation")) . ")#", $rtext)) {
                $r->general()->noConfirmation();
            }

            if (isset($general['Passengers'])) {
                $r->general()
                    ->travellers($general['Passengers']);
            }
            $r->general()
                ->status($general['Status'] ?? null)
                ->date($general['ReservationDate'] ?? null);

            if (preg_match("#^\s*\d+\s*(?<type>.+?) for \d+ Days?\s+Pick up in (?<pickUp>.+?) from (?<company>.+)#", $rtext, $m)) {
                $r->car()->type($m['type']);
                $r->pickup()
                    ->date($segText['date'])
                    ->location($m['pickUp']);
                $r->extra()->company($m['company']);
            }

            if (preg_match("#(?:^|\n)Return .*? in (?<dropoff>.+?)\s+to\s+(.+)#", $rtext, $m)) {
                $r->dropoff()
                    ->date($segText['date'])
                    ->location($m['dropoff']);
            } elseif (!preg_match("#Return .*? in #", $rtext) && !empty($cars[$key + 1]) && !empty($r->getPickUpLocation())
                    && preg_match("#(?:^|\n)Return .*? in (?<dropoff>.+?)\s+to\s+(.+)#", $cars[$key + 1]['text'], $m)) {
                $r->dropoff()
                    ->date($cars[$key + 1]['date'])
                    ->location($m['dropoff']);
            }
        }

        //###############
        //##  CRUISE  ###
        //###############
        foreach ($cruise as $key => $segText) {
            $ctext = $segText['text'];
            $guestNumbers = [];

            $c = $email->add()->cruise();

            // General
            $conf = $this->re("/{$this->opt($this->t("Conf#"))}[ ]*([A-Z\d]{4,})\s*\n*/", $ctext);

            if (!empty($conf)) {
                $c->general()
                    ->confirmation($conf, "Vendor Conf");
            } elseif (!preg_match("/{$this->opt($this->t("Conf#"))}[ ]*([A-Z\d]{4,})\s*\n*/", $ctext)) {
                $c->general()->noConfirmation();
            }
            $c->general()
                ->status($general['Status'] ?? null)
                ->date($general['ReservationDate'] ?? null);

            if (preg_match_all("#\n\s*Passengers? ([\d,\- ]+)(?=[ ]{3,}|\n)#", $ctext, $guestsNumbers)) {
                foreach ($guestsNumbers[1] as $value) {
                    $guestNumbers = array_merge($guestNumbers, $this->getGuestsNumbers($value));
                }
            }

            if (!empty($guestNumbers) && !empty($general['Passengers'])) {
                $guestNumbers = array_unique($guestNumbers);
                sort($guestNumbers);

                foreach ($guestNumbers as $key => $value) {
                    if (isset($general['Passengers'][$value])) {
                        $c->general()
                            ->traveller($general['Passengers'][$value], true);
                    }
                }
            }

            if (preg_match("#on (.*\bShip\b.* \d+)#", $ctext, $m)) {
                $c->details()
                    ->description($m[1]);
            }

            if (preg_match("#\n\s*Departing (.+) at (.+)#", $ctext, $m)) {
                $c->addSegment()
                    ->setName($m[1])
                    ->setAboard(strtotime($this->normalizeTime($m[2]), $segText['date']));
            }

            if (preg_match("#\n\s*Arriving (.+) at (.+)#", $ctext, $m)) {
                $c->addSegment()
                    ->setName($m[1])
                    ->setAshore(strtotime($this->normalizeTime($m[2]), $segText['date']));
            }

            $roomNumber = $this->re("/Cabin\s*[#]\s*(\d+)/", $ctext);

            if (!empty($roomNumber)) {
                $c->setRoom($roomNumber);
            }

            $shipName = $this->re("/Per Person Onboard Credit at the\s*(.+)\n\s*Guest/u", $ctext);

            if (!empty($shipName)) {
                $c->setShip($shipName);
            }
        }

        //#################
        //##   EVENTS   ###
        //#################
        foreach ($events as $key => $segText) {
            $rtext = $segText['text'];
            $guestNumbers = [];

            $r = $email->add()->event();

            // General
            $conf = $this->re("# " . preg_quote($this->t("Confirmation #"), '#') . "(?: CID\#?)?[ ]*([A-Z\d\-]{5,})(?:[ ]+|\n)#", $rtext);

            if (!empty($conf)) {
                $r->general()
                    ->confirmation($conf, "Confirmation");
            } elseif (!preg_match("#(" . preg_quote($this->t("Confirmation #"), '#') . ")#", $rtext)) {
                $r->general()->noConfirmation();
            }
            $r->general()
                ->status($general['Status'] ?? null)
                ->date($general['ReservationDate'] ?? null);

            if (preg_match_all("#\n\s*Guests? ([\d,\- ]+)(?:[ ]+|\n)#", $rtext, $guestsNumbers)) {
                foreach ($guestsNumbers[1] as $value) {
                    $guestNumbers = array_merge($guestNumbers, $this->getGuestsNumbers($value));
                }
            }

            if (!empty($guestNumbers) && !empty($general['Passengers'])) {
                $guestNumbers = array_unique($guestNumbers);
                sort($guestNumbers);

                foreach ($guestNumbers as $key => $value) {
                    if (isset($general['Passengers'][$value])) {
                        $r->general()
                            ->traveller($general['Passengers'][$value], true);
                    }
                }
            }

            $r->setEventType(EVENT_EVENT);

            if (preg_match("/^\s*(?<name>.+?)\s+for\s+(?<guests>\d{1,3})\s+Guests?\s+in\s+(?<addr>.+)\s+at\s+(?<time>\d{1,2}:\d{2})(?<t>[AP])/",
                $rtext, $m)) {
                $r->place()
                    ->name($m['name'])
                    ->address($m['addr']);
                $r->booked()
                    ->guests($m['guests'])
                    ->start(strtotime($m['time'] . ' ' . strtolower($m['t']) . 'm', $segText['date']));
                // Duration: 4:30 Hours    |    Duration: 0:40 Mins.
                if (preg_match("/ +Duration: (\d+):(\d+) (?:Hours|Mins)/i", $rtext, $m)) {
                    $r->booked()->end(strtotime("+ {$m[2]} minutes",
                        strtotime("+ {$m[1]} hours", $r->getStartDate())));
                }
            }
        }

        //#################
        //##  TRANSFER  ###
        //#################
        foreach ($transfers as $key => $segText) {
            $ttext = $segText['text'];
            $guestNumbers = [];

            $t = $email->add()->transfer();

            // General
            $conf = $this->re("# " . preg_quote($this->t("Vendor Conf#"), '#') . "[ ]*((?:[A-Z]{3} )?[A-Z\d\-]{5,})(?:[ ]+|\s*\n|\s*$)#", $ttext); // RH10676272    |    CAT JUN-38

            if (!empty($conf)) {
                $t->general()->confirmation(preg_replace('/\s+/', '-', $conf), "Confirmation");
            } elseif (!preg_match("#(" . preg_quote($this->t("Vendor Conf#"), '#') . ")#", $ttext)) {
                $t->general()->noConfirmation();
            }

            $t->general()
                ->status($general['Status'] ?? null)
                ->date($general['ReservationDate'] ?? null);

            $s = $t->addSegment();

            $foundDate = false;
            $from = $this->re("#\n *From:\s*(.+)#", $ttext);
            $s->departure()->name($this->normalizeAirportName($from))->noDate();

            if (stripos($from, 'airport') !== false) {
                foreach ($email->getItineraries() as $it) {
                    if ($it->getType() === 'flight') {
                        /** @var Flight $it */
                        foreach ($it->getSegments() as $itS) {
                            $this->logger->debug('$s->getArrName() = ' . print_r($itS->getArrName(), true));

                            if (strcasecmp($itS->getArrName(), $from) === 0 && $segText['date'] == strtotime('00:00', $itS->getArrDate())) {
                                $s->setDepFlightSegment($itS);
                                $foundDate = true;

                                break 2;
                            }
                        }
                    }
                }
            } else {
                $address = $this->findHotelAddress($from, $email);

                if (!empty($address)) {
                    $s->departure()
                        ->address($address);
                }
            }
            $to = $this->re("#\n *To:\s*(.+)#", $ttext);
            $s->arrival()->name($this->normalizeAirportName($to))->noDate();

            if (stripos($to, 'airport') !== false) {
                foreach ($email->getItineraries() as $it) {
                    if ($it->getType() === 'flight') {
                        /** @var Flight $it */
                        foreach ($it->getSegments() as $itS) {
                            if (strcasecmp($itS->getDepName(), $to) === 0 && $segText['date'] == strtotime('00:00', $itS->getDepDate())) {
                                $s->setArrFlightSegment($itS);
                                $foundDate = true;

                                break 2;
                            }
                        }
                    }
                }
            } else {
                $address = $this->findHotelAddress($to, $email);

                if (!empty($address)) {
                    $s->arrival()
                        ->address($address);
                }
            }

            $s->extra()->adults($this->re("#^\s*(\d+) Transfers#", $ttext));

            if ($foundDate === false) {
                $email->removeItinerary($t);
            }
        }

        return true;
    }

    private function findHotelAddress(?string $name, Email $email): string
    {
        $locationNames = [$name, trim(preg_replace('/\s*(?:Resort & Spa|Resort)\s*/', ' ', $name))];

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'hotel') {
                $hotelNames = [$it->getHotelName(), trim(preg_replace('/\s*(?:Resort & Spa|Resort)\s*/', ' ', $it->getHotelName()))];

                if (array_intersect($locationNames, $hotelNames)) {
                    return $it->getAddress();
                }
            }
        }

        return '';
    }

    private function normalizeAirportName(?string $s): string
    {
        return preg_replace('/^(.+?)([-,\s]+)(Airport)$/i', '$3$2$1', $s);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\-([^\s\d]+)\-(\d{4})$#", //04-Sep-2018
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeTime($str): string
    {
        $in = [
            "/^\s*(\d{1,2}:\d{2})([AP])\s*.*$/i", // 11:40P  ->  11:40 PM
        ];
        $out = [
            "$1 $2M",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field, $space = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) use ($space) {
            $s = preg_quote($s, '/');

            return $space ? preg_replace('/[ ]+/', '[ ]+', $s) : $s;
        }, $field)) . ')';
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function getGuestsNumbers($textNumbers)
    {
        if (empty($textNumbers)) {
            return [];
        }
        $numbers = [];
        $texts = array_filter(explode(",", $textNumbers));

        foreach ($texts as $value) {
            if (preg_match("#^\s*(\d+)\s*$#", $value, $m)) {
                $numbers[] = $m[1];
            } elseif (preg_match("#^\s*(\d+)\s*\-\s*(\d+)\s*$#", $value, $m)) {
                if ($m[2] - $m[1] > 0 && $m[2] - $m[1] < 20) {
                    for ($i = $m[1]; $i <= $m[2]; $i++) {
                        $numbers[] = $i;
                    }
                }
            }
        }

        return $numbers;
    }
}
