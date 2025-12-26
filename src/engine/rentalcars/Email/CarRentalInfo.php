<?php

namespace AwardWallet\Engine\rentalcars\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarRentalInfo extends \TAccountChecker
{
    public $mailFiles = "rentalcars/it-45185970.eml";

    public static $dictionary = [
        "en" => [
        ],
    ];

    private $detectFrom = "rentalcars.com";

    private $detectSubject = [
        "Important information regarding your Rentalcars.com booking - Ref:", // en
    ];

    private $detectBody = [
        "en" => [
            "Your car is booked:",
        ],
    ];

    private $lang = "en";

    private $rentalProviders = [
        'alamo'        => ['Alamo'],
        'avis'         => ['Avis'],
        'dollar'       => ['Dollar', 'Dollar RTA'],
        'europcar'     => ['Europcar'],
        'hertz'        => ['Hertz'],
        'localiza'     => ['Localiza'],
        'perfectdrive' => ['Budget'],
        'sixt'         => ['Sixt'],
        'thrifty'      => ['Thrifty'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $body) {
            if ($this->http->XPath->query('//*[' . $this->contains($body) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parseCar($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'rentalcars.com')]")->length == 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($dBody) . ']')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function parseCar(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Reference")) . "]/following::text()[normalize-space()][1]"));

        $r = $email->add()->rental();

        // General
        $r->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//tr[" . $this->eq($this->t("Driver details")) . "]/following-sibling::tr[" . $this->starts($this->t("Name:")) . "][1]", null, true,
                "#" . $this->preg_implode($this->t("Name:")) . "\s*(?:(?:Mr|Miss|Ms)\s*)?(.+)#"), true)
        ;

        // Pick up
        $r->pickup()
            ->date($this->normalizeDate($this->http->FindSingleNode("//tr[" . $this->eq($this->t("Pick up:")) . "]/following-sibling::tr[normalize-space()][1]")))
            ->location($this->http->FindSingleNode("//tr[" . $this->eq($this->t("Pick up:")) . "]/following-sibling::tr[normalize-space()][2]"))
        ;

        // Drop Off
        $r->dropoff()
            ->date($this->normalizeDate($this->http->FindSingleNode("//tr[" . $this->eq($this->t("Drop off:")) . "]/following-sibling::tr[normalize-space()][1]")))
            ->location($this->http->FindSingleNode("//tr[" . $this->eq($this->t("Drop off:")) . "]/following-sibling::tr[normalize-space()][2]"))
        ;

        $address = $this->http->FindSingleNode("//tr[" . $this->eq($this->t("Car hire company")) . "]/following-sibling::tr[" . $this->starts($this->t("Address:")) . " and not(preceding-sibling::tr[" . $this->eq($this->t("Driver details")) . "])][1]", null, true,
            "#" . $this->preg_implode($this->t("Address:")) . "\s*(.+?)\s*(?:\(|$)#");
        $phone = $this->http->FindSingleNode("//tr[" . $this->eq($this->t("Car hire company")) . "]/following-sibling::tr[" . $this->starts($this->t("Tel:")) . " and not(preceding-sibling::tr[" . $this->eq($this->t("Driver details")) . "])][1]", null, true,
            "#" . $this->preg_implode($this->t("Tel:")) . "\s*(.+?)\s*(?:\(|$)#");

        if (!empty($r->getPickUpLocation()) && $r->getPickUpLocation() === $r->getDropOffLocation()) {
            $r->pickup()
                ->location($address)
                ->phone($phone);
            $r->dropoff()->same();
        } else {
            $r->pickup()
                ->location($address)
                ->phone($phone);
        }

        // Car
        $xpathImg = "//tr[" . $this->eq($this->t("Pick up:")) . "]/following::img[contains(@src, 'car_images/new_images/')]";
        $r->car()
            ->type($this->http->FindSingleNode($xpathImg . "/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::tr[normalize-space(.)][1]"))
            ->model($this->http->FindSingleNode($xpathImg . "/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::tr[normalize-space(.)][2]", null, true, "#\((.+)\)#"))
            ->image($this->http->FindSingleNode($xpathImg . "/@src"))
        ;

        // Company
        $company = $this->http->FindSingleNode("//tr[" . $this->eq($this->t("Car hire company")) . "]/following-sibling::tr[" . $this->starts($this->t("Name:")) . " and not(preceding-sibling::tr[" . $this->eq($this->t("Driver details")) . "])][1]", null, true,
            "#" . $this->preg_implode($this->t("Name:")) . "\s*(.+?)\s*(?:\(|$)#");
        $company = trim($company, '* ');

        if (!empty($company)) {
            $foundCode = false;

            foreach ($this->rentalProviders as $code => $names) {
                foreach ($names as $name) {
                    if (stripos($name, $company) === 0) {
                        $r->program()->code($code);
                        $foundCode = true;

                        break 2;
                    }
                }
            }

            if ($foundCode === false) {
                $r->extra()->company($company);
            }
        }

        $total = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Total")) . "]/following-sibling::td[1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $r->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
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

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug("Date: {$date}");
        $in = [
            // 5 Jul 2019 at 10:30
            "#^\s*(\d{1,2})\s+([^\d\s\.\,]+)\s+(\d{4})[\s\D]+(\d{1,2}:\d{2})\s*$#ui",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
            '€'  => 'EUR',
            '$'  => 'USD',
            'US$'=> 'USD',
            '£'  => 'GBP',
            '₪'  => 'ILS',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
