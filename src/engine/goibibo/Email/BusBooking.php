<?php

namespace AwardWallet\Engine\goibibo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BusBooking extends \TAccountChecker
{
    public $mailFiles = "goibibo/it-52623058.eml";

    private $detectFrom = ["@goibibo.com"];
    private $detectSubject = [
        'Your Bus booking confirmation for',
    ];

    private $detectCompany = [
        'GOIBIBO',
    ];

    private $detectBody = [
        'en' => [
            'Bus Service Type', 'Pickup Point', 'Your bus booking is confirmed',
        ],
    ];

    private $lang = 'en';
    private static $dictionary = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $type = '';

        if (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(), 'Service Start Place :')])[1]"))) {
            $this->parseBus_type1($email);
            $type = '1';
        } elseif (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(), 'Depart On')])[1]"))) {
            $this->parseBus_type2($email);
            $type = '2';
        } elseif (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(), 'Your bus booking is confirmed')])[1]"))) {
            $this->parseBus_type3($email);
            $type = '3';
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $type);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($from, $detectFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        $finded = false;

        foreach ($this->detectCompany as $dCompany) {
            if (stripos($body, $dCompany) !== false) {
                $finded = true;
            }
        }

        if ($finded == false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($headers['from'], $detectFrom) !== false) {
                $finded = true;
            }
        }

        if ($finded == false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseBus_type1(Email $email)
    {
        $this->logger->notice(__METHOD__);
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'GOIBIBO TIN') or contains(normalize-space(), 'Goibibo Tin')]", null, true, "#GOIBIBO TIN\s*:\s*([\dA-Z]{5,})\s*$#i"), "GOIBIBO TIN");

        $b = $email->add()->bus();

        // General
        $b->general()
            ->confirmation($this->http->FindSingleNode("//td[normalize-space() = 'Trip Number :' or normalize-space() = 'PNR Number :']/following::td[1]", null, true, "#^\s*([\dA-Z]{5,})\s*$#"))
            ->travellers(array_filter($this->http->FindNodes("//td[normalize-space() = 'Name']/ancestor::tr[td[normalize-space()='Age']]/following-sibling::tr/td[1]", null, "#^\s*([\w ]+)\s*$#")));

        $tickets = array_unique(array_filter($this->http->FindNodes("//td[normalize-space() = 'Ticket Number :']/following::td[1]", null, "#^\s*([\d\-]{6,})\s*$#")));

        if (!empty($tickets)) {
            $b->setTicketNumbers($tickets, false);
        }

        $s = $b->addSegment();

        // Departure
        $s->departure()->name($this->http->FindSingleNode("//td[normalize-space() = 'Passenger Boarding Point :' or normalize-space() = 'Passenger Boarding Point:']/following::td[1]"));

        $date = $this->http->FindSingleNode("//td[normalize-space() = 'Date of Journey :']/following::td[1]");
        $time = $this->http->FindSingleNode("//td[normalize-space() = 'Departure time from Starting Point :' or normalize-space() = 'Departure time :']/following::td[1]");

        if (!empty($date) && !empty($time)) {
            $s->departure()->date($this->normalizeDate($date . ' ' . $time));
        }

        // Arrival
        $s->arrival()
            ->name($this->http->FindSingleNode("//td[normalize-space() = 'Passenger Alighting Point :' or normalize-space() = 'Passenger Alighting Point:']/following::td[1]"))
            ->noDate();

        // Extra
        $s->extra()->noNumber();
        $seatCol = count($this->http->FindNodes("//td[normalize-space() = 'Seat No'][preceding-sibling::td[normalize-space()='Age']]/preceding-sibling::td"));

        if (!empty($seatCol)) {
            $seats = $this->http->FindNodes("//td[normalize-space() = 'Seat No']/ancestor::tr[td[normalize-space()='Name']]/following-sibling::tr/td[" . ($seatCol + 1) . "]");

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
        } else {
            $seats = array_map('trim', explode(",", $this->http->FindSingleNode("//td[normalize-space() = 'Seat N0/s :']/following::td[1]"))); // may be another delimiter, no exsample

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
        }

        return $email;
    }

    private function parseBus_type2(Email $email)
    {
        $this->logger->notice(__METHOD__);
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//td[normalize-space() = 'Goibibo TIN']/following-sibling::td[string-length(normalize-space()) > 1][1]", null, true, "#^\s*([\dA-Z]{5,})\s*$#"), "GOIBIBO TIN");

        $b = $email->add()->bus();

        // General
        $b->general()
            ->confirmation($this->http->FindSingleNode("//td[normalize-space() = 'APSRTC PNR']/following-sibling::td[string-length(normalize-space()) > 1][1]", null, true, "#^\s*([\dA-Z]{5,})\s*$#"))
            ->travellers($this->http->FindNodes("//td[normalize-space() = 'Name']/ancestor::tr[td[normalize-space()='Age']]//following::tr[1]/ancestor::*[1]/tr/td[1]"));

        $s = $b->addSegment();

        // Departure
        $place = $this->http->FindSingleNode("//td[normalize-space() = 'From']/following-sibling::td[string-length(normalize-space()) > 1][1]");
        $point = $this->http->FindSingleNode("//td[normalize-space() = 'Pickup Point']/following-sibling::td[string-length(normalize-space()) > 1][1]");

        if (!empty($point) && strcasecmp($place, $point) !== 0) {
            $place .= ', ' . $point;
        }

        if (!empty($place)) {
            $s->departure()->name($place);
        }

        $s->departure()->date($this->normalizeDate($this->http->FindSingleNode("//td[normalize-space() = 'Depart On']/following-sibling::td[string-length(normalize-space()) > 1][1]")));

        // Arrival
        $s->arrival()
            ->name($this->http->FindSingleNode("//td[normalize-space() = 'To']/following-sibling::td[string-length(normalize-space()) > 1][1]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//td[normalize-space() = 'Arrival On']/following-sibling::td[string-length(normalize-space()) > 1][1]")));

        // Extra
        $s->extra()->noNumber();
        $seatCol = count($this->http->FindNodes("//td[normalize-space() = 'Seat No.'][preceding-sibling::td[normalize-space()='Age']]/preceding-sibling::td"));

        if (!empty($seatCol)) {
            $seats = $this->http->FindNodes("//td[normalize-space() = 'Seat No.']/ancestor::tr[td[normalize-space()='Name']]/following::tr[1]/ancestor::*[1]/tr//td[" . ($seatCol + 1) . "]");

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
        }

        return $email;
    }

    private function parseBus_type3(Email $email)
    {
        $this->logger->notice(__METHOD__);
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space() = 'Booking ID:']/following::text()[normalize-space()][1]", null, true, "#^\s*([\w]{5,})\s*$#"), "Booking ID");

        $b = $email->add()->bus();

        // price
        $sum = $this->http->FindSingleNode("//text()[contains(normalize-space(),'You have paid')]", null, false, "/You have paid (.+)/");

        if (!empty($sum)) {
            $sum = str_replace("Rs.", "INR", $sum);

            if (preg_match("/([A-Z]{3}) ([\d.,]+)/", $sum, $m)) {
                $b->price()
                    ->total(PriceHelper::cost($m[2]))
                    ->currency($m[1]);
            }
        }

        // General
        $b->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//text()[normalize-space() = 'Passanger name']/ancestor::table[contains(.,'Seat No')][1]/descendant::img/following::text()[normalize-space()!=''][1]"));

        $s = $b->addSegment();

        $routeXpath = "//tr[contains(td[1], 'hrs') and contains(td[3], 'hrs')]";
        $date = $this->normalizeDate($this->http->FindSingleNode($routeXpath . "/ancestor::tr/preceding-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()][last()]"));

        // Departure
        $depart = implode("\n", $this->http->FindNodes($routeXpath . "/td[1]//text()[normalize-space()]"));

        if (preg_match("#^\s*(.+)\s+(\d{1,2}:\d{1,2})\s*hrs\s*$#", $depart, $m)) {
            $s->departure()->name($m[1]);

            if (!empty($date)) {
                $s->departure()->date(strtotime($m[2], $date));
            }
        }
        $place = $this->http->FindSingleNode("//text()[normalize-space() = 'Boarding Point']/ancestor::td[1]", null, true, "#Boarding Point\s*(.+)#");

        if (!empty($place)) {
            $s->departure()->name((!empty($s->getDepName())) ? $place . ', ' . $s->getDepName() : $place);
        }
        $time = $this->http->FindSingleNode("//text()[normalize-space() = 'Departure time']/ancestor::td[1]", null, true, "#Departure time\s*(\d{1,2}:\d{1,2})\s*hrs\s*$#");

        if (!empty($time) && !empty($date)) {
            $s->departure()->date(strtotime($time, $date));
        }
        $address = $this->http->FindSingleNode("//text()[normalize-space() = 'Boarding Point Address']/ancestor::td[1]", null, true, "#Boarding Point Address\s*(.+)#");

        if (!empty($address)) {
            $s->departure()->address($address);
        }

        // Arrival
        $arrival = implode("\n", $this->http->FindNodes($routeXpath . "/td[3]//text()[normalize-space()]"));

        if (preg_match("#^\s*(.+)\s+(\d{1,2}:\d{1,2})\s*hrs\s*$#", $arrival, $m)) {
            $s->arrival()->name($m[1]);

            if (!empty($date)) {
                $s->arrival()->date(strtotime($m[2], $date));
            }
        }

        if ($s->getDepDate() && $s->getArrDate() && $s->getArrDate() < $s->getDepDate()) {
            $s->arrival()->date(strtotime("+1 day", $s->getArrDate()));
        }

        // Extra
        $s->extra()->noNumber();
        $seats = $this->http->FindNodes("//text()[normalize-space() = 'Passanger name']/ancestor::table[contains(.,'Seat No')][1]/descendant::img/following::text()[normalize-space()!=''][not(contains(.,'Seat No'))][2]");

        if (!empty($seats)) {
            $s->extra()->seats($seats);
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s+(\d{1,2}:\d{2})\s*Hrs\s*$#", // 07/10/2018 12:00 Hrs
            "#^\s*[^\d\s]+\s+([^\d\s]+),\s*(\d{1,2})\s+(\d{4})\s+(\d{1,2}:\d{2})\s*$#", // Wednesday Jan, 23 2019 08:35
            "#^\s*[^\d\s]+,\s*([^\d\s]+)\.\s*(\d{1,2})[a-z]{2},\s*(\d{4})\s+(\d{1,2}:\d{2})\s*hrs\a*$#", // Wednesday, Jan. 16th, 2019 08:30 hrs
            "#^\s*[^\d\s]+\s*(\d{1,2})\s+([^\d\s]+)\s+(\d{2})\s*$#", // Fri, 30 Nov 18
        ];
        $out = [
            "$1.$2.$3, $4",
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
