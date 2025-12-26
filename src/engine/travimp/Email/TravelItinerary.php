<?php

namespace AwardWallet\Engine\travimp\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelItinerary extends \TAccountChecker
{
    public $mailFiles = "travimp/it-23591385.eml, travimp/it-27948827.eml";
    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = 'travimp.com';
    private $detectSubject = [
        ' Booking: ',
    ];

    private $detectCompany = [
        'Travel Impressions',
    ];

    private $detectBody = [
        'en' => [
            'www.travimp.com',
        ],
    ];

    private $lang = 'en';

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

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        $findedCompany = false;

        foreach ($this->detectCompany as $detectBody) {
            if (stripos($body, $detectBody) !== false) {
                $findedCompany = true;

                break;
            }
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
        if (stripos($headers['from'], $this->detectFrom) === false) {
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

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    protected function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail(Email $email)
    {
        $userEmail = $this->http->FindSingleNode("(//text()[normalize-space(.)='Contact Email:']/ancestor::td[1]/following-sibling::td[1]//text()[contains(., '@')]/ancestor::a[1])[1]", null, true, "#^\s*([^@]+@[^@]+)\s*$#");

        if (!empty($userEmail)) {
            $email->setUserEmail($userEmail);
        }

        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space(.)='Booking Number:']/ancestor::td[1]/following-sibling::td[1]", null, true, "#^\s*([\dA-z]{5,})\s*$#"));

        // Segment
        $xpath = "//text()[normalize-space(.)='TRAVEL ITINERARY']/ancestor::table[1]/following-sibling::table[last()]/descendant::tr[1]/ancestor::*[1]/tr[normalize-space()]["
                    . "td["
                        . "position()<3][starts-with(translate(normalize-space(), '0123456789', '|||||||||||'), '||')] "
                        . "and following-sibling::tr[normalize-space()][1]"
                        . "[string-length(normalize-space(td[1])) < 1 and string-length(normalize-space(td[2])) < 1 and not(td[position()<3][starts-with(translate(normalize-space(), '0123456789', '|||||||||||'), '||') or starts-with(normalize-space(), 'Destination')])"
                    . "]"
                . "]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->info("Segments root not found: {$xpath}");

            return $email;
        }

        foreach ($segments as $root) {
            if (!empty($this->http->FindSingleNode(".//text()[contains(.,' Locator:')]", $root))) {
                $this->flight($email, $root);

                continue;
            }

            if (!empty($tr = $this->http->FindSingleNode("./following::tr[normalize-space()][1]", $root))
                    && preg_match("#^\s*\d+ Nts #", $tr)) {
                $this->hotel($email, $root);

                continue;
            }

            if (!empty($this->http->FindSingleNode("./following::tr[normalize-space()][position()<3][contains(., 'Pickup:')]", $root))) {
                $this->rental($email, $root);

                continue;
            }
            $this->logger->debug("itinerary type is not detected:" . $root->nodeValue);
            $f = $email->add()->flight(); // for 100% failed

            return $email;
        }

        $travellers = $this->http->FindNodes("//text()[normalize-space() = 'Passenger Name(s)']/ancestor::tr/following-sibling::tr/td[position()<3][normalize-space()][1]//text()[not(./ancestor::a)][normalize-space()]");

        if (!empty($travellers)) {
            foreach ($email->getItineraries() as $value) {
                $value->general()->travellers($travellers);
            }
        }

        $total = $this->getTotal($this->http->FindSingleNode("(//text()[normalize-space(.)='Vacation Amount:']/ancestor::td[1]/following-sibling::td[1])[1]"));

        if (!empty(array_filter($total))) {
            $email->price()
                ->total($total['Amount'])
                ->currency($total['Currency']);
        }

        return $email;
    }

    private function flight(Email $email, $root)
    {
        $found = false;

        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'flight') {
                $s = $value->addSegment();
                $found = true;

                break;
            }
        }

        if ($found == false) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->noConfirmation();

            $s = $f->addSegment();
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("./td[normalize-space()][1]", $root, true, "#(.+?)(?:\s*-\s*.+)?$#"));

        if (empty($date)) {
            return $email;
        }
        // Airline
        $s->airline()
            ->name($this->http->FindSingleNode("./td[normalize-space()][2]", $root, true, "#(.+?)\s+(?:Bulk|Published) Airfare#"))
            ->number($this->http->FindSingleNode("./following-sibling::tr[1]/td[contains(., 'Flight Number:')]", $root, true, "#:\s*(\d{1,5})\b#"))
            ->confirmation($this->http->FindSingleNode(".//text()[contains(., 'Locator:')]/following::text()[normalize-space()][1]", $root, true, "#^\s*([A-Z\d]{5,7})\s*$#"))
        ;
        $al = $this->http->FindSingleNode(".//text()[contains(., 'Locator:')]", $root, true, "#^\s*([A-Z\d]{2}) Locator:#");

        if (!empty($al)) {
            $s->airline()->name($al);
        }

        // Departure
        $s->departure()
            ->noCode()
            ->name($this->http->FindSingleNode("./following-sibling::tr[1]/td[contains(., 'Depart:')]", $root, true, "#:\s*(.+?)\s*\d+:\d+#"))
            ->date(strtotime($this->http->FindSingleNode("./following-sibling::tr[1]/td[contains(., 'Depart:')]", $root, true, "#:\s*.+?\s*(\d+:\d+.+)#"), $date))
        ;

        $date2 = $this->http->FindSingleNode("./td[normalize-space()][1]", $root, true, "#.+?\s*-\s*(\S.+)$#");

        if (!empty($date2)) {
            $date = $this->normalizeDate($date2);
        }

        // Arrival
        $s->arrival()
            ->noCode()
            ->name($this->http->FindSingleNode("./following-sibling::tr[2]/td[contains(., 'Arrive:')]", $root, true, "#:\s*(.+?)\s*\d+:\d+#"))
            ->date(strtotime($this->http->FindSingleNode("./following-sibling::tr[2]/td[contains(., 'Arrive:')]", $root, true, "#:\s*.+?\s*(\d+:\d+.+)#"), $date))
        ;

        // Extra
        $s->extra()
            ->bookingCode($this->http->FindSingleNode("./following-sibling::tr[2]/td[contains(., 'Arrive:')]/preceding-sibling::td[normalize-space()][1]", $root, true, "#^\s*([A-Z])\s+Class\s*$#"))
        ;
        $seats = array_filter(explode(" ", $this->http->FindSingleNode("./following-sibling::tr[2]/td[normalize-space()][last()]", $root)), function ($v) { if (preg_match("#^\s*\d{1,3}[A-Z]\s*$#", $v)) {return true; } else {return false; }});

        if (!empty($seats)) {
            $s->extra()->seats($seats);
        }

        return $email;
    }

    private function hotel(Email $email, $root)
    {
        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("./td[normalize-space()][3]", $root, true, "#\#\s*([A-Za-z\d]{5,})(?:\s+|/|$)#");

        if (!empty($conf)) {
            $h->general()->confirmation($conf);
        } else {
            $h->general()->noConfirmation();
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("./td[normalize-space()][2]", $root))
            ->noAddress()
        ;

        // Booked
        $date = $this->http->FindSingleNode("./td[normalize-space()][1]", $root);

        if (preg_match("#(.+) - (.+)#", $date, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]))
            ;
        } elseif (!empty($date)) {
            $h->booked()
                ->checkIn($this->normalizeDate($date))
                ->checkOut(strtotime("+1 day", $h->getCheckInDate()))
            ;
        }

        $h->addRoom()
            ->setType($this->http->FindSingleNode("./following-sibling::tr[2]/td[normalize-space()][1]", $root, true, "#:\s*(.+)#"))
        ;

        return $email;
    }

    private function rental(Email $email, $root)
    {
        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("./following-sibling::tr[2]/td[normalize-space()][last()]", $root, true, "#^\s*CF-\s*([A-Z\d]{5,})\s*$#"))
        ;

        // PickUp
        $r->pickup()
            ->location($this->http->FindSingleNode("./following-sibling::tr[2]/td[contains(., 'Pickup:')]", $root, true, "#:\s*[\d/]+\s*\d+:\d+\s*[AP]M\s+(.+)#"))
            ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[2]/td[contains(., 'Pickup:')]", $root, true, "#:\s*([\d/]+\s*\d+:\d+\s*[AP]M)\s+.+#")))
        ;

        // Drop Off
        $r->dropoff()
            ->location($this->http->FindSingleNode("./following-sibling::tr[3]/td[contains(., 'Drop Off:')]", $root, true, "#:\s*[\d/]+\s*\d+:\d+\s*[AP]M\s+(.+)#"))
            ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[3]/td[contains(., 'Drop Off:')]", $root, true, "#:\s*([\d/]+\s*\d+:\d+\s*[AP]M)\s+.+#")))
        ;

        // Car
        $r->car()
            ->type($this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space()][1]", $root, true, "#^\s*\d+\s*Day\(s\)\s*(.+)#"));

        // Extra
        $r->extra()
            ->company($this->http->FindSingleNode("./td[normalize-space()][2]", $root, true, "#(.+?)(\-\w.*)$#"));

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
            "#^\s*(\d+)([^\d\s\.\,]+)(\d{2})\s*$#ui", // 02Sep18
            "#^\s*(\d+)/(\d+)/(\d{4})\s+(\d+:\d+(\s*[ap]m)?)\s*$#ui", // 10/22/2018 12:00PM
        ];
        $out = [
            "$1 $2 $3",
            "$2.$1.$3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function getTotal($price)
    {
        $result = ["Amount" => null, "Currency" => null];

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<total>\d[\d\., ]*)\s*$#", $price, $m)
                || preg_match("#^\s*(?<total>\d[\d\. ,]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $price, $m)) {
            $result = [
                "Amount"   => $this->amount($m['total']),
                "Currency" => $this->currency($m['curr']),
            ];
        }

        return $result;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

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
}
