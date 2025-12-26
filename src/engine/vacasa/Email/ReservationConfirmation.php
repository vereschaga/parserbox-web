<?php

namespace AwardWallet\Engine\vacasa\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "vacasa/it-161251190.eml, vacasa/it-60844259.eml, vacasa/it-60865892.eml, vacasa/it-67520354.eml, vacasa/it-67520365.eml, vacasa/it-67677071.eml";

    private $detectFrom = "info@vacasa.com";
    private $detectSubject = [
        "Reservation Confirmation - ", //Reservation Confirmation - Welches - Wednesday, June 17, 2020 - Saturday, June 20, 2020
        "Checking in to your ",
        " invited you to ",
    ];
    private $detectCompany = '.vacasa.com/';
    private $detectBody = [
        "en" => [
            "Log in to your online account to make changes",
            "It's almost time for your trip",
            "has invited you to stay at their Vacasa home in",
            "'s Vacasa home is coming up soon.",
        ],
    ];

    private $date;
    private $lang = "en";
    private static $dictionary = [
        'en' => [
            "Confirmation" => ["Confirmation", "Reservation", "Reservation #:"],
            //            "Check-in:" => "",
            //            "Check-out:" => "",
            //            "Guests:" => "",
            "adult"    => ["adult", "adults"],
            "children" => ["children", "kids"],
            //            "Total Cost:" => "",
            //            "Reservation Name:" => "",
            //            "We hope you're excited for your trip to" => "",
        ],
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

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, $this->detectCompany) === false) {
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

    private function parseEmail(Email $email)
    {
        // Travel Agency
        $conf = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Confirmation")) . "]/following::text()[normalize-space()])[1]", null, true, "/^#?\s*([A-Z\d\-]{5,}|\d{5,})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Confirmation")) . "])[1]", null, true, "#{$this->preg_implode($this->t("Confirmation"))}\s*([A-Z\d\-]{5,})\b#");
        }
        $email->ota()
            ->confirmation($conf)
        ;

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
        ;
        $traveller = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation Name:")) . "]/following::text()[normalize-space()][1]");

        if (!empty($traveller)) {
            $h->general()->traveller($traveller);
        }

        // Hotel
        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Confirmation"))}]/ancestor::h2[1]/following-sibling::strong[normalize-space()][1]");
        $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Confirmation"))}]/ancestor::h2[1]/following-sibling::a[normalize-space()][1]");



        if (empty($name) && empty($address)) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Get there"))}]/ancestor::h2[1]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][1][not({$this->eq($this->t("Rental:"))})]");
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Get there"))}]/ancestor::h2[1]/following-sibling::*/a[normalize-space()][1]");
        }

        if (empty($name) && empty($address)) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Rental:"))}]/following-sibling::b/a[normalize-space()][1]");
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Address:"))}]/following-sibling::b/a[normalize-space()][1]");
        }
        
        $h->hotel()
            ->name($name)
            ->address($address)
        ;

        // Booked
        $adults = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Guests:"))}]/following::text()[normalize-space()][1]");

        if (preg_match("/{$this->preg_implode($this->t('adult'))}\s*(\d+)/i", $adults, $m)
            || preg_match("/(\d+)\s*{$this->preg_implode($this->t('adult'))}/i", $adults, $m)) {
            $h->booked()->guests($m[1]);
        }
        $kids = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guests:")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("/{$this->preg_implode($this->t('children'))}\s*(\d+)/i", $kids, $m)
            || preg_match("/(\d+)\s*{$this->preg_implode($this->t('children'))}/i", $kids, $m)) {
            $h->booked()->kids($m[1]);
        }
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t("Check-in:"))}]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-out:")) . "]/following::text()[normalize-space()][1]")))
        ;

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Cost:")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            //4:00 PM, Wednesday, July 08, 2020
            '#^\s*(\d{1,2}:\d{2}(?:\s*[ap]m))\s*,\s*\w+,\s*(\w+)\s+(\d+)\s*,\s*(\d{4})\s*\(?$#iu',
        ];
        $out = [
            '$3 $2 $4, $1',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
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
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
