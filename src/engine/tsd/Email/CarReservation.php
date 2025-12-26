<?php

namespace AwardWallet\Engine\tsd\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarReservation extends \TAccountChecker
{
    public $mailFiles = "tsd/it-86947846.eml, tsd/it-88892597.eml";
    public $lang = "en";

    public static $dictionary = [
        "en" => [
            "Confirmation No." => ["R/A No.", "Confirmation No."],
            "Reservation"      => ["Reservation", "Rental Agreement"],
        ],
    ];

    private $detectFrom = 'mailer@tsdnotify.com';
    private $detectCompany = 'tsdnotify.com';

    private $detectSubject = [
        // en
        " - Confirmation No.:",
    ];

    private $detectBody = [
        "en" => ["This Reservation is valid until", "This Rental Agreement is valid until"],
    ];

    private $rentalProviders = [
        'thrifty' => [
            'THRIFTY CAR RENTAL',
        ],
        'dollar' => [
            'DOLLAR RENT A CAR',
        ],
        'foxrewards' => [
            'FOX RENT A CAR',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

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
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectedCompany = false;

        if ($this->http->XPath->query("//a[" . $this->contains($this->detectCompany, '@href') . "] | //img[" . $this->contains($this->detectCompany, '@src') . "] | //*[" . $this->contains($this->detectCompany) . "]")->length > 0) {
            $detectedCompany = true;
        }

        if ($detectedCompany === false
                && $this->http->XPath->query("//tr[td[1][" . $this->eq("LESSOR") . "] and td[2][" . $this->eq("RENTER") . "]]")->length > 0
        ) {
            $detectedCompany = true;
        }

        if ($detectedCompany === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
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

    protected function getDateFormat($date1, $date2, $date3)
    {
        if (preg_match("/\b(\d{1,2})\/(\d{2})\/(\d{4})\b/", $date1, $m)) {
            if ($m[1] > 12) {
                return true;
            }

            if ($m[2] > 12) {
                return false;
            }
        }

        if (preg_match("/\b(\d{1,2})\/(\d{2})\/(\d{4})\b/", $date2, $m)) {
            if ($m[1] > 12) {
                return true;
            }

            if ($m[2] > 12) {
                return false;
            }
        }

        if (preg_match("/\b(\d{1,2})\/(\d{2})\/(\d{4})\b/", $date3, $m)) {
            if ($m[1] > 12) {
                return true;
            }

            if ($m[2] > 12) {
                return false;
            }
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        $email->obtainTravelAgency();

        $r = $email->add()->rental();

        $bookedDate = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booked Date")) . "]/following::text()[normalize-space(.)][1]");
        $pickupDate = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Pick-up (date & time):")) . "]/following-sibling::td[normalize-space(.)][1]");
        $dropoffDate = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Drop off (date & time):")) . "]/following-sibling::td[normalize-space(.)][1]");
        $dateFormatDM = $this->getDateFormat($bookedDate, $pickupDate, $dropoffDate);

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation No.")) . "]/following::text()[normalize-space(.)][1]"))
            ->date($this->normalizeDate($bookedDate, $dateFormatDM))
            ->traveller(preg_replace('/^\s*([^,]+?)\s*,\s*([^,]+?)\s*$/', '$2 $1',
                $this->http->FindSingleNode("//tr[td[2][not(.//td) and " . $this->eq($this->t("RENTER")) . "]]/following-sibling::tr[normalize-space(.)][1]/td[2]/descendant::text()[normalize-space()][1]", null, true,
                    "/^[^,]+,[^,]+/")), true)
        ;

        // Provider
        $rentalCompany = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation No.")) . "]/preceding::text()[normalize-space()][1]", null,
            false, "/^\s*(.+?)[\-\s*]+" . $this->preg_implode($this->t("Reservation")) . "\s*$/");

        if (!empty($rentalCompany)) {
            $r->extra()->company($rentalCompany);

            foreach ($this->rentalProviders as $code => $detects) {
                foreach ($detects as $detect) {
                    if ($rentalCompany === $detect) {
                        $r->setProviderCode($code);

                        break 2;
                    }
                }
            }
        }

        // Pick Up
        $location = implode("\n", $this->http->FindNodes("//tr[td[1][not(.//td) and " . $this->eq($this->t("LESSOR")) . "]]/following-sibling::tr[normalize-space(.)][1]/td[1]//text()[normalize-space()]"));

        if (preg_match("/\n([\d\(\)\-\+ ]{6,})\(Fax\)\s*$/", $location, $m)) {
            $r->pickup()
                ->fax($m[1]);
            $location = str_replace($m[0], '', $location);
        }

        if (preg_match("/([\s\S]+)\n([\d\(\)\-\+ ]{6,})\(\w\)\s*$/", $location, $m)) {
            $r->pickup()
                ->location(preg_replace('/\s+/', ' ', trim($m[1])))
                ->phone($m[2])
            ;
        } else {
            $r->pickup()
                ->location(preg_replace('/\s+/', ' ', trim($location)))
            ;
        }
        $r->pickup()
            ->date($this->normalizeDate($pickupDate, $dateFormatDM))
        ;

        // Drop Off
        $r->dropoff()
            ->same()
            ->date($this->normalizeDate($dropoffDate, $dateFormatDM))
        ;

        // Car
        $r->car()
            ->type($this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Unit Class:")) . "]/following-sibling::td[normalize-space(.)][1]"))
        ;

        $total = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Total Charges")) . "]/following-sibling::td[normalize-space(.)][1]");

        if (preg_match('/^\s*(\d[\d, ]*(\.\d{2})?)\s*$/', $total, $m)) {
            $r->price()
                ->total(PriceHelper::cost($m[1]))
                ->currency('USD');
        }

        $taxes = $this->http->XPath->query("//tr[td[1][not(.//td) and " . $this->eq($this->t("Total Charges")) . "]]/preceding-sibling::tr");

        foreach ($taxes as $root) {
            $name = $this->http->FindSingleNode("td[1]", $root, true, "/(.*\b(?:Tax|Fee)\b.*)/i");
            $value = $this->http->FindSingleNode("td[2]", $root, true, "/^\s*(\d[\d, ]*(\.\d{2})?)\s*$/");

            if (!empty($name) && !empty($value)) {
                $r->price()
                    ->fee($name, PriceHelper::cost($value))
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

    private function contains($field, $text = 'normalize-space(.)')
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($date, $dm = false)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            '/^\s*(\d{1,2})\/(\d{2})\/(\d{4})(.*)$/u',
        ];

        if ($dm) {
            $out = [
                '$1.$2.$3$4',
            ];
        } else {
            $out = [
                '$2.$1.$3$4',
            ];
        }
        $str = strtotime(preg_replace($in, $out, $date));

        return $str;
    }
}
