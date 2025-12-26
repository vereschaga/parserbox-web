<?php

namespace AwardWallet\Engine\rentals\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3 extends \TAccountChecker
{
    public $mailFiles = "rentals/it-1.eml, rentals/it-2218355.eml, rentals/it-3.eml, rentals/it-4.eml, rentals/it-68663073.eml";
    public static $dict = [
        'en' => [
            'TOTAL ESTIMATED CHARGES' => ['TOTAL ESTIMATED CHARGES', 'Total amount of your booking'],
        ],
    ];

    private $detectFrom = "@carrentals.com";

    private $detectSubject = [
        'CarRentals.com Car Reservation',
        'Your car rental reservation', //Your car rental reservation 1615539912COUNT has been cancelled
    ];

    private $detectBody = [
        'en' => [['Reservation Details', 'Name:']],
    ];

    private $rentalProviders = [
        'alamo'      => ['Alamo'],
        'foxrewards' => ['Fox'],
        'rentacar'   => ['Enterprise'],
    ];

    private $emailDate;
    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->emailDate = strtotime($parser->getDate());
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'http://carrentals.com/') or contains(@href, '.carrentals.com/') ]")->length < 2) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (is_array($dBody) && count($dBody) == 2
                    && $this->http->XPath->query("//text()[" . $this->eq($dBody[0]) . "]/following::text()[normalize-space()][" . $this->eq($dBody[1]) . "]")->length < 2) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!empty($headers['from']) && !empty($headers["subject"])) {
            if (stripos($headers['from'], $this->detectFrom) === false) {
                return false;
            }

            foreach ($this->detectSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $email->obtainTravelAgency();

        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation Code")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*([A-Z\d]+\d[A-Z\d]+)\s*$/"))
            ->traveller($this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][" . $this->eq($this->t("Name:")) . "]]/descendant::text()[normalize-space()][2]"))
        ;

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("Cancellation Confirmation")) . "])[1]"))) {
            $r->general()
                ->status('Cancelled')
                ->cancelled();
        }

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("Your reservation is confirmed")) . "])[1]"))) {
            $r->general()
                ->status('confirmed');
        }

        // Pick up
        $location = array_filter($this->http->FindNodes("//td[descendant::text()[normalize-space()][1][" . $this->eq($this->t("Pick-up Location:")) . " or " . $this->eq(preg_replace("/\s*:$/", '', $this->t("Pick-up Location:"))) . "]]/descendant::text()[normalize-space()][position() > 1]"));
        $this->logger->debug('pi $date = ' . print_r($location, true));
        $puDate = null;

        if (!empty($location) && $r->getCancelled() && preg_match("/\b20\d{2}\b/", $location[0])) {
            $puDate = $this->normalizeDate($location[0]);
            unset($location[0]);
        }

        $r->pickup()
            ->location(implode(", ", $location))
            ->date($puDate ?? $this->normalizeDate(implode(' ', $this->http->FindNodes("//td[descendant::text()[normalize-space()][1][" . $this->eq($this->t("Pick-up Date/Time:")) . "]]/node()[position() > 2]"))))
        ;

        // Drop off
        $location = array_filter($this->http->FindNodes("//td[descendant::text()[normalize-space()][1][" . $this->eq($this->t("Drop-off Location:")) . " or " . $this->eq(preg_replace("/\s*:$/", '', $this->t("Drop-off Location:"))) . "]]/descendant::text()[normalize-space()][position() > 1]"));
        $doDate = null;
        $this->logger->debug('do $date = ' . print_r($location, true));

        if (!empty($location) && $r->getCancelled() && preg_match("/\b20\d{2}\b/", $location[0])) {
            $doDate = $this->normalizeDate($location[0]);
            unset($location[0]);
        }

        $r->dropoff()
            ->location(implode(", ", $location))
            ->date($doDate ?? $this->normalizeDate(implode(' ', $this->http->FindNodes("//td[descendant::text()[normalize-space()][1][" . $this->eq($this->t("Drop-off Date/Time:")) . "]]/descendant::text()[normalize-space()][position() > 1]"))))
        ;

        // Extra
        $company = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation Code")) . "]", null, true,
            "/^\s*(.+) " . $this->preg_implode($this->t("Confirmation Code")) . "\s*:?\s*$/");

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

        // Car
        $r->car()
            ->model($this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][" . $this->eq($this->t("Vehicle Type:")) . " or " . $this->eq(preg_replace("/\s*:$/", '', $this->t("Vehicle Type:"))) . "]]/descendant::text()[normalize-space()][2]"))
        ;

        // Price
        $r->price()
            ->total($this->amount($this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("TOTAL ESTIMATED CHARGES")) . "]/following-sibling::td[normalize-space()][1]", null, true,
                "/\D*(\d[\d., ]*)\b/")))
            ->currency($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Prices are in")) . "]", null, true,
                "/" . $this->preg_implode($this->t("Prices are in")) . "\s+([A-Z]{3})\b/"))
            ->tax($this->amount($this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Estimated taxes & fees")) . "]/following-sibling::td[normalize-space()][1]", null, true,
                "/\D*(\d[\d., ]*)\b/")))
        ;

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->emailDate);
        $in = [
            // Tuesday March 17, 2020, 7:00PM
            '#^\s*\w+\s+(\w+)\s+(\d+)\s*,?\s*\b(\d{4})\s*,?\s*\b(\d{1,2}:\d{2}(?:\s*[ap]m)?)$#iu',
            // Wednesday, December 31, 10:00 AM
            '#^\s*(\w+)\s*,\s*(\w+)\s+(\d+)\s*,?\s*\b(\d{1,2}:\d{2}(?:\s*[ap]m)?)$#iu',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$1, $3 $2 ' . $year . ', $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v); }, $field)) . ')';
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
