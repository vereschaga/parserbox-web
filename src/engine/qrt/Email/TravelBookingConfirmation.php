<?php

namespace AwardWallet\Engine\qrt\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelBookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "qrt/it-12632969.eml, qrt/it-13184866.eml, qrt/it-13654231.eml, qrt/it-13955162.eml, qrt/it-13956603.eml, qrt/it-13980315.eml, qrt/it-14382843.eml, qrt/it-64919356.eml";

    public $lang = "en";
    private $reFrom = "@qrt.bz";
    private $reSubject = [
        "en"=> "Travel Booking Confirmation",
    ];
    private $reBody = [
        'en'  => ['Thank you for choosing us to book your', 'Flight Details'],
        'en2' => ['Thank you for choosing us to book your', 'Car Rental Information'],
        'en3' => ['Thank you for choosing us to book your', 'Hotel Room Details'],
    ];

    private static $dictionary = [
        "en" => [
            "Total Paid:"=> ["Total Paid:", "Total Paid in Points :"],
        ],
    ];
    private $date = null;

    private $flightCompany = [
        'british'     => 'British Airways',
        'mileageplus' => 'United',
        'skywards'    => 'Emirates',
        'delta'       => 'Delta',
        'jetblue'     => 'JetBlue Airways',
        'aa'          => 'American',
        'ufly'        => 'Sun Country',
        'alaskaair'   => 'Alaska Airlines',
    ];
    private $carCompany = [
        'alamo' => 'Alamo',
    ];

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

        foreach ($this->reBody as $re) {
            if (strpos($body, $re[0]) !== false && strpos($body, $re[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re[0]) !== false && strpos($this->http->Response["body"], $re[1]) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }
        $email->setType($a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang));

        $this->parseHtml($email);

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

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        return null;
    }

    protected function parseHtml(Email $email)
    {
        // Travel Agency
        $phone = $this->nextText(["Give us a call", "Give us a call at"]);

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[" . $this->starts("Give us a call") . "][1]", null, true, "#Give us a call at\s*([\d\+\-\(\). ]{5,})\b#");
        }
        $email->ota()
            ->phone($phone)
            ->confirmation($this->nextText("Agency Record Locator"), "Agency Record Locator");

        // Price
        if (!empty($s = $this->nextText("Base Fare :")) && stripos($s, 'Points') === false) {
            $email->price()->cost($this->amount($s));
            $email->price()->currency($this->currency($s));
        }

        if (!empty($s = $this->nextText("Fees & Taxes:")) && stripos($s, 'Points') === false) {
            $email->price()->tax($this->amount($s));
            $email->price()->currency($this->currency($s));
        }

        if (!empty($s = $this->nextText("Total Paid:")) && stripos($s, 'Points') !== false) {
            $email->price()->spentAwards($s);
        } elseif (!empty($s = $this->http->FindSingleNode("//text()[" . $this->starts("Total Paid:") . "]", null, true, "#Total Paid:\s*(.+Points.*)#"))) {
            $email->price()->spentAwards($s);
        } else {
            $total = $this->http->FindSingleNode("//text()[" . $this->starts("Total Paid ") . "]/following::text()[normalize-space()][1][contains(., 'Points')]");

            if (!empty($total)) {
                $email->price()->spentAwards($total);
            }
            $total = $this->http->FindSingleNode("//text()[" . $this->starts("Total Paid ") . "]/following::text()[normalize-space()][1][not(contains(., 'Points'))]");

            if (!empty($total)) {
                $email->price()->total($this->amount($total));
                $email->price()->currency($this->currency($total));
            }
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains(["Flight Details", "flight details"]) . "])[1]"))) {
            $this->flights($email);
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains(["Car Rental Information", "car details"]) . "])[1]"))) {
            $this->cars($email);
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains(["Hotel Information", "Hotel Room Details"]) . "])[1]"))) {
            $this->hotels($email);
        }

        return $email;
    }

    protected function flights(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $confirmations = array_map('trim', explode(",", $this->nextText("Airline Reservation Code")));

        foreach ($confirmations as $value) {
            $f->general()->confirmation($value);
        }
        $f->general()
            ->travellers($this->http->FindNodes("//text()[translate(normalize-space(.), '1234567890', 'dddddddddd')='Passenger d :']/following::text()[normalize-space(.)][1]", null, "#(.*?)(?: \(|$)#"), true);

        $providers = array_unique(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(.), 'Itinerary Managed By')]", null, "#:\s*(.+)#")));

        if (count($providers) == 1) {
            $company = array_shift($providers);
            $findProvider = false;

            foreach ($this->flightCompany as $code => $name) {
                if ($company == $name) {
                    $f->program()->code($code);
                    $findProvider = true;
                }
            }

            if ($findProvider == false) {
                $f->program()->keyword($company);
            }
        }
        $seats = [];
        $seatsText = $this->http->FindNodes("//text()[" . $this->eq("Requested Seat(s)") . "]/following::table[1]//tr[not(.//tr) and count(./td) = 2]/td[2]");

        if (empty($seatsText)) {
            $seatsText = $this->http->FindNodes("//text()[" . $this->eq("Requested Seat(s)") . "]/following::table[1]//tr[not(.//tr)]//text()[normalize-space()]", null, "#^[\w\- ]+? ([\w\-]+,.+)$#");
        }

        foreach ($seatsText as $seattext) {
            foreach (explode(", ", $seattext) as $i=>$seat) {
                if (preg_match("#^\s*(\d{1,3})-([A-Z])\s*$#", $seat, $m)) {
                    $seats[$i][] = $m[1] . $m[2];
                } else {
                    $seats[$i][] = null;
                }
            }
        }
        // Segments
        $segments = $this->split("#\n(.* \d{4}\n.*?, \w{2} \#\d+\n)#", implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(.), 'Depart ')]/ancestor::td[1]//text()[normalize-space()]")));

        foreach ($segments as $i=> $stext) {
            $s = $f->addSegment();
            $date = $this->normalizeDate($this->re("#^(.*?)\n#", $stext));
            $s->departure()
                ->noCode()
                ->name($this->re("#Depart (.*?) at \d+:\d+ [AP]M#", $stext))
                ->date(strtotime($this->re("#Depart .*? at (\d+:\d+ [AP]M)#", $stext), $date));
            $s->arrival()
                ->noCode()
                ->name($this->re("#Arrive (.*?) at \d+:\d+ [AP]M#", $stext))
                ->date(strtotime($this->re("#Arrive .*? at (\d+:\d+ [AP]M)#", $stext), $date));

            if ($adate = $this->re("#Next day arrival - (.+)#", $stext)) {
                $adate = $this->normalizeDate($adate);
                $s->arrival()->date(strtotime($this->re("#Arrive .*? at (\d+:\d+ [AP]M)#", $stext), $adate));
            }

            $s->airline()
                ->name($this->re("#, (\w{2}) \#\d+#", $stext))
                ->number($this->re("#, \w{2} \#(\d+)#", $stext));
            $s->extra()->cabin($this->re("#Class of Service: (.+)#", $stext), true, true);

            if (count($seats) == count($segments) && !empty(array_filter($seats[$i]))) {
                $s->extra()->seats(array_filter($seats[$i]));
            }
        }

        return $email;
    }

    protected function cars(Email $email)
    {
        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->nextText("Reservation Code"))
            ->traveller($this->nextText("Driver:"), true);

        // Pick Up
        $r->pickup()
            ->date(strtotime($this->http->FindSingleNode("(//text()[normalize-space(.) = 'Pickup Location']/following::text()[normalize-space(.) = 'Date & Time'])[1]/ancestor::td[1]", null, true, "#Date & Time\s*(.+)#")))
            ->location($this->http->FindSingleNode("//text()[normalize-space(.) = 'Pickup Address']/ancestor::td[1]", null, true, "#Pickup Address\s*(.+)#"));

        // Drop Off
        $r->dropoff()
            ->date(strtotime($this->http->FindSingleNode("(//text()[normalize-space(.) = 'Drop-Off Location']/following::text()[normalize-space(.) = 'Date & Time'])[1]/ancestor::td[1]", null, true, "#Date & Time\s*(.+)#")))
            ->location($this->http->FindSingleNode("//text()[normalize-space(.) = 'Drop-Off Address']/ancestor::td[1]", null, true, "#Drop-Off Address\s*(.+)#"));

        // Car
        $r->car()
            ->model($this->http->FindSingleNode("//text()[normalize-space(.) = 'Car Make Model']/ancestor::td[1]", null, true, "#Car Make Model\s*(.+)#"))
            ->type($this->http->FindSingleNode("//text()[normalize-space(.) = 'Car Class']/ancestor::td[1]", null, true, "#Car Class\s*(.+)#"));

        // Extra
        $company = trim($this->http->FindSingleNode("//text()[normalize-space(.) = 'Vendor Name']/ancestor::td[1]", null, true, "#Vendor Name\s*(.+)#"));
        $findProvider = false;

        foreach ($this->carCompany as $code => $name) {
            if ($company == $name) {
                $r->program()->code($code);
                $findProvider = true;
            }
        }

        if ($findProvider == false) {
            $r->extra()->company($company);
        }

        return $email;
    }

    protected function hotels(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $confs = array_filter(array_map('trim', explode(',', $this->nextText("Hotel Reservation Code"))));

        if (!empty($confs)) {
            foreach ($confs as $conf) {
                $h->general()
                    ->confirmation($conf, "Hotel Reservation Code");
            }
        }
        $h->general()
            ->traveller($this->nextText("Guest Name :"), true);

        // Hotel
        $h->hotel()
            ->name($this->nextText("Hotel Name :"))
            ->address($this->nextText("Hotel Address :"));

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->nextText("Check-in :")))
            ->checkOut(strtotime($this->nextText("Check-out :")));

        $checkInOutTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-In time is')]");

        if (preg_match("/^Check-In time is\s*([\d\:]+\s*A?P?M)\,\s*Check-Out Time is\s*([\d\:]+\s*A?P?M)\.$/", $checkInOutTime, $m)) {
            $h->booked()
                ->checkIn(strtotime($this->nextText("Check-in :") . ', ' . $m[1]))
                ->checkOut(strtotime($this->nextText("Check-out :") . ', ' . $m[2]));
        }

        $xpath = "//text()[normalize-space() = 'Hotel Room Details']/ancestor::tr[1]/following-sibling::tr//td[starts-with(normalize-space(), 'Room') and not(.//td)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $h->booked()
                ->rooms($nodes->count()); //+1 - ?
        }
        $refundable = false;
        $guests = 0;

        foreach ($nodes as $root) {
            $text = implode("\n", $this->http->FindNodes(".//text()", $root));
            $this->logger->warning($text);
            $r = $h->addRoom();

            if (preg_match("#Room \d+\s*:\s+(.+?), (.+)#", $text, $m)) {
                $r
                    ->setType($m[1])
                    ->setDescription($m[2]);
            }
            //64919356
            if (preg_match("#Room\s+\d+\s*:\s+(.+?)\nGuests#u", $text, $m)) {
                $r->setType($m[1]);
            }

            if (preg_match("#\n\s*Non-Refundable\s+#", $text, $m)) {
                $refundable = true;
            } else {
                $refundable = false;
            }

            if (preg_match("#Guests\s*:\s+(\d+)#", $text, $m)) {
                $guests += $m[1];
            }
        }

        if (!empty($guests)) {
            $h->booked()
                ->guests($guests);
        }

        if ($refundable == true) {
            $h->booked()
                ->nonRefundable(true);
        }

        return $email;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        // $this->http->log($instr);
        $in = [
            "#^[^\s\d]+, ([^\s\d]+) (\d+), (\d{4})$#", //Sun, Oct 07, 2018
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
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
}
