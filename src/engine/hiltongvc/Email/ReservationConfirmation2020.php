<?php

namespace AwardWallet\Engine\hiltongvc\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation2020 extends \TAccountChecker
{
    public $mailFiles = "hiltongvc/it-61021182.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "hiltongrandvacations.com";

    private $detectSubject = [
        "en" => "Your HGV Club Reservation",
    ];
    private $detectCompany = "Hilton Grand Vacations Inc";
    private $detectBody = [
        "en" => "YOUR RESERVATION HAS BEEN CONFIRMED",
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        $body = html_entity_decode($this->http->Response["body"]);
//        foreach($this->detectBody as $lang => $dBody){
//            if (stripos($body, $dBody) !== false) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
//        if (empty($headers["from"]) || empty($headers["subject"])) {
        if (empty($headers["subject"])) {
            return false;
        }

//        if (stripos($headers["from"], $this->detectFrom) === false) {
//            return false;
//        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (strpos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
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

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextText($this->t("Reservation Number:")), "Reservation Number");

        $travellersLastNames = $this->nextText($this->t("Last Name:"));
        $travellersFirstNames = array_map(function ($v) use ($travellersLastNames) {
            return $v . ' ' . $travellersLastNames;
        }, preg_split("#\s*[&/]\s*#", $this->nextText($this->t("First Name:"))));
        $h->general()
            ->travellers($travellersFirstNames, true);

        if (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'YOUR RESERVATION HAS BEEN CONFIRMED')])[1]"))) {
            $h->general()->status('confirmed');
        }

        // Hotel
        $hotelName = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Arrival Date:")) . "]/preceding::text()[normalize-space()][3][./ancestor::strong]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Arrival Date:")) . "]/preceding::text()[normalize-space()][2]");
        }

        $address = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Arrival Date:")) . "]/preceding::text()[normalize-space()][2][./preceding::text()[normalize-space()][1]/ancestor::strong]");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Arrival Date:")) . "]/preceding::text()[normalize-space()][1]");
        }
        $h->hotel()
            ->name($hotelName)
            ->address($address);

        $phone = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Arrival Date:")) . "]/preceding::text()[normalize-space()][1][./preceding::text()[normalize-space()][2]/ancestor::strong]");

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextText($this->t("Arrival Date:")) . ', ' . $this->nextText($this->t("Check-In Time:"))))
            ->checkOut($this->normalizeDate($this->nextText($this->t("Departure Date:")) . ', ' . $this->nextText($this->t("Check-Out Time:"))))
            ->guests($this->nextText($this->t("No. of Guests:"), null, "#^\s*(\d) adult#"))
            ->kids($this->nextText($this->t("No. of Guests:"), null, "#\b(\d) child#"), true, true);

        $h->addRoom()->setType($this->nextText($this->t("Unit Type:")));

        // Program
        $account = $this->nextText($this->t("Member Number:"));

        if (!empty($account)) {
            $h->program()->account($account, false);
        }

        // Price
        $spent = $this->nextText($this->t("Points Used:"));

        if (!empty($spent)) {
            $h->price()->spentAwards($spent . ' Points');
        }

        $feeNames = ["Reservation Fee:", "Cancellation Protection Fee:", "Guest Certificate Fee:"];

        foreach ($feeNames as $feeName) {
            $value = $this->nextText($feeName);

            if (empty($value)) {
                continue;
            }

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $value, $m)) {
                $h->price()
                    ->fee(trim($feeName, ':'), $this->amount($m['amount']))
                    ->currency($this->currency($m['curr']))
                ;
            }
        }
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
        //$this->http->log($str);
        $in = [
            "#^\s*(\d{1,2}\.\d{1,2}\.\d{4})\s*\(.* (\d+:\d+) Uhr\)\s*$#", //25.11.2018 (Check-in ab 14:00 Uhr)
            "#^([\d\/]+)\,\D{2,}.*$#u",
        ];
        $out = [
            "$1 $2",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);
//        if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
//            if($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }
        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nextText($field, $root = null, $regexp = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regexp);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if ($s === $f) {
                return $r;
            }
        }

        return null;
    }
}
