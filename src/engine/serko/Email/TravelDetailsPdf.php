<?php

namespace AwardWallet\Engine\serko\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelDetailsPdf extends \TAccountChecker
{
    public $mailFiles = "serko/it-28816542.eml, serko/it-29138367.eml";

    public static $detectHeaders = [
        'fcmtravel' => [
            'froms'   => ['fcmtravel'],
            'subject' => ['Travel Details for'],
        ],
        'campustr' => [
            'froms'   => ['campustravel'],
            'subject' => ['Travel Details for'],
        ],
        'ctraveller' => [
            'froms'   => ['corporatetraveller'],
            'subject' => ['Travel Details for'],
        ],
    ];
    private $detectCompany = [
        'campustr' => [
            'CAMPUS TRAVEL', 'campustravel',
        ],
        'ctraveller' => [
            'corporatetraveller.',
        ],
        'fcmtravel' => [
            'FCM TRAVEL', 'fcmtravel',
        ],
    ];
    private $detectBody = [
        'itinerary.rpt',
    ];
    private $pdfPattern = '.*\.pdf';
    private $providerCode;
    private $lang = 'en';

    private $travellers;

    public function parseEmail(Email $email, string $allText)
    {
        $allText = preg_replace("#\n[ ]*(?:Continued on next page[ ]*|.*[ ]+itinerary\.rpt[ ]+Page \d+ of \d+.*)(?:\n|$)#", "\n", $allText);
        $text = $allText;

        // Travel agency
        if (preg_match("#\s+Online Reference:[ ]*([A-Z\d]{5,})\s*#", $text, $m)) {
            $email->ota()
                ->confirmation($m[1], "Online Reference");
        }

        if (preg_match("#tinerary prepared for[ ]*(.+)#", $text, $m)) {
            $this->travellers[] = $m[1];
        }

        $pricingText = '';

        if (stripos($text, 'Pricing') !== false && preg_match("#(.+)\n([ ]{0,20}Pricing(?:[ ]{10,}[^\n]*|\n).+\n\s*Total[ ]*\(([A-Z]{3})\)[ ]*:[ ]*(\d[\d,.]*)\s*(?:\n|$))#s", $text, $m)) {
            $text = $m[1];
            $pricingText = $m[2];
        }
        $dateFormal = "[[:alpha:]]{6,9} \d{1,2} [[:alpha:]]{3,9} \d{4}";
        $text = preg_replace("#\n([ ]{0,15}" . $dateFormal . "[ ]*\(continued\.\.\.\))#", "\n", $text);
        $segmentsByDay = $this->split("#\n([ ]{0,15}" . $dateFormal . "\n)#", $text);
//        $this->logger->debug('Segments By Day = '.print_r( $segmentsByDay,true));

        $segments = [];

        foreach ($segmentsByDay as $key => $dayText) {
            $date = $this->normalizeDate($this->re("#^\s*(.+?)(?:\(|\n)#", $dayText));

            if (empty($date)) {
                return false;
            }
            $dayText = preg_replace('#^\s*.+?(?:\(.*\n|\n)([\s\S]+)#', "$1", $dayText);
            $segmentsL = $this->split("#(?:^|\n)\s*("
                    . "\d{1,2}:\d{2} [ap]\.m. .*\n\s*Departs:" // flight depart
                    . "|\d{1,2}:\d{2} [ap]\.m.[ ]*Arrive .+? on flight" // flight arrive
                    . "|.+\n\s*Address:" // hotel check-in
                    . "|Check-out .+" // hotel check-out
                    . "|\d{1,2}:\d{2} [ap]\.m. .*\n\s*Pick-up: " // rental pick-up
                    . "|\d{1,2}:\d{2} [ap]\.m.[ ]*Drop-off .+" // rental drop-off
                    . ")#u", $dayText, false);
            $segments = array_merge($segments, array_map(function ($v) use ($date) {return ["date" => $date, "text" => $v]; }, $segmentsL));
        }
//        $this->logger->debug('Segments = '.print_r( $segments,true));

        $flights = [];
        $hotels = [];
        $rentals = [];
        $companies = [];

        foreach ($segments as $i => $stext) {
            switch (true) {
                case $v = $this->re("#^\s*\d{1,2}:\d{2} [ap]\.m. (.+)[ ]*-[ ]*Flight.*\n\s*Departs:#", $stext['text']):
                    $this->logger->debug("segment-$i: FLIGHT");
                    $flights[] = $stext;

                    break;

                case $this->re("#^(\s*\d{1,2}:\d{2} [ap]\.m.[ ]*Arrive .+? on flight)#", $stext['text']):
                    $this->logger->debug("segment-$i: FLIGHT");

                    break;

                case $v = $this->re("#^\s*(.+)\n\s*Address:#", $stext['text']):
                    $this->logger->debug("segment-$i: HOTEL");
                    $companies[] = trim($v);
                    $hotels[] = $stext;

                    break;

                case $this->re("#^(\s*Check-out .+)#", $stext['text']):
                    $this->logger->debug("segment-$i: HOTEL");

                    break;

                case $v = $this->re("#^\s*\d{1,2}:\d{2} [ap]\.m. (.+)\n\s*Pick-up:#", $stext['text']):
                    $this->logger->debug("segment-$i: RENTAL");
                    $companies[] = trim($v);
                    $rentals[] = $stext;

                    break;

                case $this->re("#^(\s*\d{1,2}:\d{2} [ap]\.m.[ ]*Drop-off )#", $stext['text']):
                    $this->logger->debug("segment-$i: RENTAL");

                    break;

                default:
                    $this->logger->debug("segment-$i: unknown type\n" . $stext['text']);

                    return false;
            }
        }

        if (!empty($flights)) {
            $this->flight($email, $flights);
        }

        if (!empty($hotels)) {
            $this->hotel($email, $hotels);
        }

        if (!empty($rentals)) {
            $this->rental($email, $rentals);
        }

        // Price
        if (!empty($pricingText)) {
            $currency = '';

            if (preg_match("#\n\s*Total[ ]*\(([A-Z]{3})\)[ ]*:[ ]*(\d[\d,.]*)\s*\n#", $pricingText, $m)) {
                $email->price()
                    ->total($this->amount($m[2]))
                    ->currency($m[1]);
                $currency = $m[1];
            }

            if (count($companies) !== count(array_unique($companies))) {
                $companies = array_diff($array, array_diff_assoc($array, array_unique($array))); // delete dublicated
            }

            if (!empty($companies) || !empty($flightCompany)) {
                foreach ($email->getItineraries() as $key => $it) {
                    if ($it->getType() == 'flight' && $it->getPrice() && !empty($it->getPrice()->getTotal())) {
                        $it->price()->currency($currency);
                    }

                    if (!empty($companies) && $it->getType() == 'hotel' && !empty($it->getHotelName()) && in_array($it->getHotelName(), $companies)
                            && preg_match("#(?:^|\n)\s*" . $it->getHotelName() . ":[ ]+(.+)#", $pricingText, $m)) {
                        $it->price()
                            ->total($this->amount($m[1]))
                            ->currency($currency);
                    }

                    if (!empty($companies) && $it->getType() == 'rental' && !empty($it->getCompany()) && in_array($it->getCompany(), $companies)
                            && preg_match("#(?:^|\n)\s*" . $it->getCompany() . ":[ ]+(.+)#", $pricingText, $m)) {
                        $it->price()
                            ->total($this->amount($m[1]))
                            ->currency($currency);
                    }
                }
            }
        }

        return $email;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (empty($pdfs)) {
            $this->http->Log('Pdf is not found');

            return false;
        }
        $its = [];

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->parseEmail($email, $text);

            if (empty($this->providerCode)) {
                $this->providerCode = $this->detectProvider(implode(" ", $parser->getFrom()), $text);
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$detectHeaders as $providerCode => $detectHeaders) {
            if (empty($detectHeaders['froms']) || empty($detectHeaders['subject'])) {
                continue;
            }
            $foundFrom = false;

            foreach ($detectHeaders['froms'] as $pFrom) {
                if (stripos($headers['from'], $pFrom) !== false) {
                    $foundFrom = true;

                    if (empty($this->providerCode)) {
                        $this->providerCode = $providerCode;
                    }

                    break;
                }
            }

            if ($foundFrom == false) {
                continue;
            }

            foreach ($detectHeaders['subject'] as $pSubject) {
                if (stripos($headers['subject'], $pSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            foreach ($this->detectCompany as $providerCode => $detectCompany) {
                $foundCompany = false;

                foreach ($detectCompany as $dCompany) {
                    if (stripos($text, $dCompany) !== false) {
                        $foundCompany = true;
                        $this->providerCode = $providerCode;

                        break 2;
                    }
                }
            }

            if ($foundCompany === false) {
                continue;
            }

            foreach ($this->detectBody as $dBody) {
                if (stripos($text, $dBody) !== false) {
                    return true;
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName('SERKO_[\w\-]+\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            foreach ($this->detectBody as $dBody) {
                if (stripos($text, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectHeaders as $provider) {
            if (!empty($provider['froms'])) {
                foreach ($provider['froms'] as $pFrom) {
                    if (stripos($from, $pFrom) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectHeaders);
    }

    private function flight(Email $email, array $flights)
    {
//        $this->logger->debug('Flight Segments = '.print_r( $flights,true));

        $airs = [];

        foreach ($flights as $stext) {
            $ticket = $this->re("#Ticket No:[ ]*(\d{3})\d+\b#", $stext['text']);
            $airs[$ticket][] = $stext;
        }

        foreach ($airs as $fsegments) {
            $f = $email->add()->flight();

            // General
            $f->general()->noConfirmation();

            if (!empty($this->travellers)) {
                $f->general()->travellers($this->travellers, true);
            }

            $tickets = [];
            $total = 0.0;
            $errorTotalFlight = false;

            foreach ($fsegments as $key => $segText) {
                $stext = $segText['text'];

                $s = $f->addSegment();

                // Airline
                if (preg_match("# Flight[ ]*([A-Z\d]{2})(\d+)#", $stext, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                // Departure
                if (preg_match("#\n\s*Departs:[ ]*([A-Z]{3})[ ]*-[ ]*(.+)(\d{4})[ ]*hrs#", $stext, $m)) {
                    $s->departure()
                        ->code($m[1])
                        ->name($m[2])
                        ->date(strtotime($this->normalizeTime($m[3]), $segText['date']));
                }

                if (preg_match("#Departs:.*\(([^\)]*Terminal[^\)]*)\)[\S\s]*Arrives:#", $stext, $m)) {
                    $s->departure()->terminal(trim(preg_replace("#Terminal\s*#iu", '', $m[1])));
                }

                // Arrival
                if (preg_match("#\n\s*Arrives:[ ]*([A-Z]{3})[ ]*-[ ]*(.+)(\d{4})[ ]*hrs#", $stext, $m)) {
                    $s->arrival()
                        ->code($m[1])
                        ->name($m[2])
                        ->date(strtotime($this->normalizeTime($m[3]), $segText['date']));
                }

                if (preg_match("#Arrives:.*\(([^\)]*Terminal[^\)]*)\)#", $stext, $m)) {
                    $s->arrival()->terminal(trim(preg_replace("#Terminal\s*#iu", '', $m[1])));
                }

                unset($pos);
                $pos[] = 0;

                if (preg_match("#(?:^|\n)(.* )(?:Status:|Class:|Sector Fare:|Meals:)#", $stext, $m)) {
                    $pos[] = strlen($m[1]);
                }

                if (count($pos) !== 2) {
                    $this->logger->debug('table parse is failed. Segment: ' . $stext);

                    return $email;
                }

                $table = $this->SplitCols($stext, $pos);

                if (preg_match("#Airline Ref:[ ]+([A-Z\d]{5,7})(?:\s+|$)#", $table[0], $m)) {
                    $s->airline()
                        ->confirmation($m[1]);
                }

                // Extra
                if (preg_match("#Flying Time:[ ]*(.*?hrs)#", $table[0], $m)) {
                    $s->extra()->duration($m[1]);
                }

                if (preg_match("#Aircraft:[ ]*([\s\S]+?)(?:\n\n|\n.+:[ ]+|$)#", $table[0], $m)) {
                    $s->extra()->aircraft($this->prettyPrint($m[1]));
                }

                if (preg_match("#Status:[ ]*([\s\S]+?)(?:\n\n|\n.+:[ ]+|$)#", $table[1], $m)) {
                    $s->extra()->status($this->prettyPrint($m[1]));
                }

                if (preg_match("#Class:[ ]*([\s\S]+?)(?:\n\n|\n.+:[ ]+|$)#", $table[1], $m)) {
                    if (preg_match("#(.+) ([A-Z]{1,2})\s*(?:$|\(.+\)\s*$)#s", $m[1], $mat)) {
                        $s->extra()
                            ->cabin(trim($mat[1]))
                            ->bookingCode($mat[2]);
                    }
                }

                if (preg_match("#Meals:[ ]*([\s\S]+?)(?:\n\n|\n.+:[ ]+|$)#", $table[1], $m)) {
                    $s->extra()->meal($this->prettyPrint($m[1]));
                }

                if (preg_match("#Sector Fare:[ ]*(.+)#", $table[1], $m)) {
                    $total += $this->amount($m[1]);
                } else {
                    $errorTotalFlight = true;
                }

                // Issued
                if (preg_match_all("#Ticket No:[ ]*(\d{10,})#", $stext, $m)) {
                    $tickets = array_merge($tickets, $m[1]);
                }
            }

            $tickets = array_values(array_unique(array_filter($tickets)));

            if (!empty($tickets)) {
                $f->issued()->tickets($tickets, false);
            }
            unset($tickets);

            if ($errorTotalFlight === false && !empty($total)) {
                $f->price()->total($total);
            }
        }

        return $email;
    }

    private function hotel(Email $email, array $hotels)
    {
//        $this->logger->debug('Hotel Segments = '.print_r( $hotels,true));
        foreach ($hotels as $segment) {
            $stext = $segment['text'];
            $h = $email->add()->hotel();

            // General
            if (preg_match("#\s+Confirmation No:[ ]*([A-Z\d\:]{5,})[\-]*\s*(?:\n|$)#", $stext, $m)) { //Confirmation No: 1275354:24
                $h->general()
                    ->confirmation(str_replace(":", '', $m[1]));
            }

            if (!empty($this->travellers)) {
                $h->general()->travellers($this->travellers, true);
            }

            unset($pos);
            $pos[] = 0;

            if (preg_match("#(?:^|\n)(.* )(?:Status:|Check-in:|Check-out:|Room Type:)#", $stext, $m)) {
                $pos[] = strlen($m[1]);
            }

            if (count($pos) !== 2) {
                $this->logger->debug('table parse is failed. Segment: ' . $stext);

                return $email;
            }
            $table = $this->SplitCols($stext, $pos);

            if (preg_match("#Status:[ ]*(.+)#", $table[1], $m)) {
                $h->general()->status($m[1]);
            }

            // Hotel
            $h->hotel()
                ->name($this->re("#^(.+)#", $stext));
            $h->hotel()
                ->address(preg_replace("#\s*\n\s*#", ", ", trim($this->re("#Address:[ ]*([\s\S]+?\n)\s*(?:Phone:|Fax:|.+:[ ]{2,})#", $table[0]))));

            if (preg_match("#Phone:[ ]*([\d\+\-\(\) ]+)(?:\n|$)#", $table[0], $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("#Fax:[ ]*([\d\+\-\(\) ]+)(?:\n|$)#", $table[0], $m)) {
                $h->hotel()->fax($m[1]);
            }

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->re("#Check-in:[ ]*(.+)#", $table[1])))
                ->checkOut($this->normalizeDate($this->re("#Check-out:[ ]*(.+)#", $table[1])));

            // Rooms
            if (preg_match("#Room Type:[ ]*([\s\S]+?\n)\s*(?:\n\n|Average Room Rate:|Confirmation No:|.+:[ ]{2,})#", $table[1], $m)) {
                $h->addRoom()->setType($this->prettyPrint($m[1]));
            }

            if (preg_match("#\n\s*Average Room Rate:[ ]*\(([A-Z]{3})\)[ ]*(\d[\d,.]*)\s*\n#", $table[1], $m)) {
                if (!empty($h->getRooms()[0])) {
                    $h->getRooms()[0]->setRate($m[2] . ' ' . $m[1]);
                } else {
                    $h->addRoom()->setRate($m[2] . ' ' . $m[1]);
                }
            }
        }

        return $email;
    }

    private function rental(Email $email, array $rentals)
    {
//        $this->logger->debug('Rental Segments = '.print_r( $rentals,true));
        foreach ($rentals as $segment) {
            $stext = $segment['text'];
            $r = $email->add()->rental();

            // General
            if (preg_match("#\s+Confirmation No:[ ]*([A-Z\d]{5,})[\-]*\s*(?:\n|$)#", $stext, $m)) {
                $r->general()
                    ->confirmation($m[1]);
            }

            if (!empty($this->travellers)) {
                $r->general()->travellers($this->travellers, true);
            }

            // Extra
            $r->extra()
                ->company($this->re("#^\s*\d+:\d+[apm\. ]* (.+)#", $stext));

            unset($pos);
            $pos[] = 0;

            if (preg_match("#(?:^|\n)(.* )(?:Status:|Car Type:|Car Rate:|Confirmation No:)#", $stext, $m)) {
                $pos[] = strlen($m[1]);
            }

            if (count($pos) !== 2) {
                $this->logger->debug('table parse is failed. Segment: ' . $stext);

                return $email;
            }

            $table = $this->SplitCols($stext, $pos);

            if (preg_match("#Status:[ ]*(.+)#", $table[1], $m)) {
                $r->general()->status($m[1]);
            }

            // Pick Up
            $locationRegexp = "#^.*\(([^\(]+)\)\s*Depot Address:\s*(.+)#s";

            if (preg_match("#Pick-up:[ ]*([\s\S]+?\n)\s*Drop-off:#", $table[0], $m)) {
                if (preg_match($locationRegexp, $m[1], $mat)) {
                    $r->pickup()
                        ->date($this->normalizeDate($mat[1]))
                        ->location(preg_replace("#\s*\n\s*#", ", ", trim($mat[2])));
                }
            }

            // Drop Off
            if (preg_match("#Drop-off:[ ]*([\s\S]+?\n)\s*(?:Rate Includes:|Service Comment:|$)#", $table[0], $m)) {
                if (preg_match($locationRegexp, $m[1], $mat)) {
                    $r->dropoff()
                        ->date($this->normalizeDate($mat[1]))
                        ->location(preg_replace("#\s*\n\s*#", ", ", trim($mat[2])));
                }
            }

            // Car
            if (preg_match("#Car Type:[ ]*([\s\S]+?\n)\s*(?:\n\n|Car Rate:|Confirmation No:|.+:[ ]{2,})#", $table[1], $m)) {
                $r->car()->model($this->prettyPrint($m[1]));
            }
        }

        return $email;
    }

    private function detectProvider($from, $text)
    {
        foreach (self::$detectHeaders as $providerCode => $detectHeaders) {
            if (empty($detectHeaders['froms'])) {
                continue;
            }

            foreach ($detectHeaders['froms'] as $pFrom) {
                if (stripos($from, $pFrom) !== false) {
                    return $providerCode;
                }
            }
        }

        foreach ($this->detectCompany as $providerCode => $detectCompany) {
            foreach ($detectCompany as $providerCode => $dCompany) {
                if (stripos($text, $dCompany) !== false) {
                    return $providerCode;
                }
            }
        }

        return null;
    }

    private function split($re, $text)
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

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map('preg_quote', $field));
    }

    private function amount($s)
    {
        $s = preg_replace("#,(\d{3})#", "$1", $s);

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d+)\s+([^\d\s\.\,]+)\s+(\d{4})\s+(\d{1,2})(\d{2})\s*hrs\s*$#u", // 19 Nov 2018 1800 hrs
        ];
        $out = [
            "$1 $2 $3 $4:$5",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}

        return strtotime($str);
    }

    private function normalizeTime($str)
    {
        // $this->http->log($str);
        $in = [
            "#^\s*(\d{2})(\d{2})\s*$#i", //1140
        ];
        $out = [
            "$1:$2",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function prettyPrint($string)
    {
        return preg_replace("#\s+#", " ", trim($string));
    }
}
