<?php

namespace AwardWallet\Engine\hotwire\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HConfirmation extends \TAccountChecker
{
    public $mailFiles = "hotwire/it-11053266.eml, hotwire/it-11102310.eml, hotwire/it-11212857.eml";

    public $reBody = [
        'en'  => ['Review your Hotwire itinerary', 'Your Hotwire confirmation number is'],
        'en2' => ['shared an itinerary with you', 'because your email address was provided for this Hotwire account'],
    ];
    public $reSubject = [
        '#\s*Your\s+stay\s+in\s+(.+?)\s+is\s+confirmed\s*$#i',
        '#Hotwire\s+Booking\s+Confirmation#',
        '#wants\s+to\s+share\s+info\s+about\s+a\s+hotel\s+they\s+booked\s+on\s+Hotwire#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    private $date;

    public function ParseEmail(Email $email)
    {
        $tripNumber = $this->http->FindSingleNode("//text()[contains(.,'Your Hotwire confirmation number')]", null, true, "#number\s*is\s+([A-Z\d]{7,})#");

        if (empty($tripNumber)) {
            $tripNumber = $this->http->FindSingleNode("//text()[contains(.,'Your Hotwire confirmation number')]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]+)\s*$#");
        }

        if (!empty($tripNumber)) {
            $email->ota()
                ->confirmation($tripNumber);
        }

        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[contains(.,'Reservation details')]/ancestor::table[1]/following-sibling::table[contains(.,'Primary guest')]//td[2]//strong[1]"));

        $confNumber = $this->http->FindSingleNode("//text()[contains(.,'Reservation details')]/ancestor::table[1]/following-sibling::table[contains(.,'Hotel confirmation')]//td[2]");

        if (empty($confNumber)) {
            $confNumber = $this->re('/Hotel\s+confirmation\s+number\s+is\s+(\d+)/', $this->http->FindSingleNode("//text()[contains(normalize-space(),'Hotel confirmation number is')]/ancestor::div[1]"));
        }

        if (strpos($confNumber, ',') !== false) {
            $h->general()
                ->confirmation(trim(explode(',', $confNumber)[0]));
        } else {
            $h->general()
                ->confirmation($confNumber);
        }

        $hotelName = $this->http->FindSingleNode("//img[contains(@src, '_star.png') or contains(@src, '_star_')]/ancestor::td[1]/descendant::div[string-length(normalize-space(.))>3][1]");
        $nodes = $this->http->FindNodes("//img[contains(@src, '_star.png') or contains(@src, '_star_')]/ancestor::td[1]/descendant::text()[normalize-space()]");
        $address = preg_replace('/\s+/', ' ', trim($this->re("/{$hotelName}\s+(.+)[(]/s", implode("\n", $nodes))));
        $phone = $this->http->FindSingleNode("//img[contains(@src, '_star.png') or contains(@src, '_star_')]/ancestor::td[1]/descendant::div[string-length(normalize-space(.))>3][last()]", null, true, '/([(]\d+[)]\s+\d+[-]\d+)/');

        if (empty($hotelName) && empty($address) && empty($phone)) {
            $hotelName = $this->http->FindSingleNode("//text()[contains(.,'Your Hotwire confirmation number is')]/ancestor::table[1]/following-sibling::table[2]/descendant::td[1]/descendant::div[string-length(normalize-space(.))>3][1]");
            $address = $this->http->FindSingleNode("//text()[contains(.,'Your Hotwire confirmation number is')]/ancestor::table[1]/following-sibling::table[2]/descendant::td[1]/descendant::div[string-length(normalize-space(.))>3][2]");
            $phone = $this->http->FindSingleNode("//text()[contains(.,'Your Hotwire confirmation number is')]/ancestor::table[1]/following-sibling::table[2]/descendant::td[1]/descendant::div[string-length(normalize-space(.))>3][3]");
        }

        if (empty($address) && empty($phone) && !empty($hotelName)) {
            $address = $this->http->FindSingleNode("//strong[normalize-space()='{$hotelName}']/ancestor::div[1]/following::div[contains(normalize-space(), ',')][1]");
            $phone = $this->http->FindSingleNode("//strong[normalize-space()='{$hotelName}']/ancestor::div[1]/following::div[contains(normalize-space(), '-')][1]");
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone);

        $checkInDateFull = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(.,'Reservation details')]/ancestor::table[1]/following-sibling::table[contains(.,'Check-in')]//td[2]", null, true, "#(\S+\s+\d+,\s+\d{4}.+)#i")));

        if (empty($checkInDateFull)) {
            $checkInDate = $this->http->FindSingleNode("//a[starts-with(normalize-space(),'Check-in')]/ancestor::div[1]/following::a[1]");
            $checkInTime = $this->re('/After\s(.+)/', $this->http->FindSingleNode("//a[starts-with(normalize-space(),'Check-in')]/ancestor::div[1]/following::a[2]"));

            if (!empty($checkInDate) && !empty($checkInTime)) {
                $checkInDateFull = strtotime($checkInDate . ' ' . $checkInTime);
            }
        }

        $h->booked()
            ->checkIn($checkInDateFull);

        $checkOutDateFull = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(.,'Reservation details')]/ancestor::table[1]/following-sibling::table[contains(.,'Check-out')]//td[2]", null, true, "#(\S+\s+\d+,\s+\d{4}.+)#i")));

        if (empty($checkOutDateFull)) {
            $checkOutDate = $this->http->FindSingleNode("//a[starts-with(normalize-space(),'Check-out')]/ancestor::div[1]/following::a[1]");
            $checkOutTime = $this->re('/Before\s(.+)/', $this->http->FindSingleNode("//a[starts-with(normalize-space(),'Check-out')]/ancestor::div[1]/following::a[2]"));

            if (!empty($checkOutDate) && !empty($checkOutTime)) {
                $checkOutDateFull = strtotime($checkOutDate . ' ' . $checkOutTime);
            }
        }

        $h->booked()
            ->checkOut($checkOutDateFull);

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[contains(.,'Reservation details')]/ancestor::table[1]/following-sibling::table[contains(.,'Room type')]//td[2]", null, true, "#(\d+)\s*room\(s#"))
            ->guests($this->http->FindSingleNode("//text()[contains(.,'Reservation details')]/ancestor::table[1]/following-sibling::table[contains(.,'Number of guests')]//td[2]", null, true, "#Adults\s*:?\s*(\d+)#i"));

        if ($roomType = $this->http->FindSingleNode("//text()[contains(.,'Reservation details')]/ancestor::table[1]/following-sibling::table[contains(.,'Room type')]//td[2]", null, true, "#(.+?)\s*(?:\+\s+Add\s+a\s+room|)$#i")) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        if ($tax = $this->http->FindSingleNode("//text()[contains(.,'Price summary')]/ancestor::table[1]/following-sibling::table[contains(.,'Taxes and fees')]//td[2]")) {
            $h->price()
                ->tax($tax);
        }

        if ($cost = $this->http->FindSingleNode("//text()[contains(.,'Price summary')]/ancestor::table[1]/following-sibling::table[contains(.,'Room price')]//td[2]", null, true, "#\b(\d[\d\.\, ]+)\s+per night#")) {
            $h->price()
                ->cost(str_replace(',', '', $cost));
        }

        if ($discount = $this->http->FindSingleNode("//text()[contains(.,'Price summary')]/ancestor::table[1]/following-sibling::table[contains(.,'Hot Dollar applied')]//td[2]")) {
            $h->price()
                ->discount(str_replace('-', '', $discount));
        }
        $total = $this->http->FindSingleNode("//text()[contains(.,'Price summary')]/ancestor::table[1]/following-sibling::table[contains(.,'Total')]//td[2]//strong");
        $currency = $this->http->FindSingleNode("//text()[contains(.,'Price summary')]", null, true, "#\(\s*([A-Z]{3})\s*\)#");

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total(str_replace(',', '', $total))
                ->currency($currency);
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'Hotwire')]")->length > 0
            || $this->http->XPath->query("//img[contains(@alt,'hotwire')]")->length > 0
        ) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "hotwire.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    protected function AssignLang($body)
    {
        $this->lang = "";

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        if (empty($this->lang)) {
            return false;
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\S+\s+\d+,\s+\d+\s+\d+:\d+\s*[ap]m)\b.*#i',
            '#^\s*(\S+\s+\d+,\s+\d{4})\s+\+.+#i',
        ];
        $out = [
            '$1',
            '$1',
        ];
        $date = preg_replace($in, $out, $date);

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
