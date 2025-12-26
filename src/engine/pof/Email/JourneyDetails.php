<?php

namespace AwardWallet\Engine\pof\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class JourneyDetails extends \TAccountChecker
{
    public $mailFiles = "pof/it-134830021.eml, pof/it-136683493.eml, pof/it-678935262.eml";

    public $detectFrom = "@poferries.com";
    public $detectBody = [
        'en' => ['Below you will find all the important information regarding your upcoming trip'],
    ];
    public $detectSubject = [
        'Booking confirmation',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'feesNames' => ['Environmental Charge'],
        ],
    ];

    public $ports = [
        'LAR' => [
            'prefix' => 'GB',
            'name'   => 'LARNE P&O Ferries, United Kingdom',
        ],
        'CYN' => [
            'prefix' => 'GB',
            'name'   => 'Cairnryan Port P&O Ferries, United Kingdom',
        ],
        'DUB' => [
            'prefix' => 'IE',
            'name'   => 'Dublin Port P&O Ferries, Ireland',
        ],
        'LIV' => [
            'prefix' => 'GB',
            'name'   => 'Liverpool Port P&O Ferries, United Kingdom',
        ],
        'TEE' => [
            'prefix' => 'GB',
            'name'   => 'TEESPORT Port P&O Ferries, United Kingdom',
        ],
        'EUR' => [
            'prefix' => 'NL',
            'name'   => 'EUROPOORT Port P&O Ferries, Netherlands',
        ],
        'HUL' => [
            'prefix' => 'GB',
            'name'   => 'Hull Port P&O Ferries, United Kingdom',
        ],
        'ZEE' => [
            'prefix' => 'BE',
            'name'   => 'Zeebrugge Port P&O Ferries, Belgium',
        ],
        'TIL' => [
            'prefix' => 'GB',
            'name'   => 'Tilbury Port P&O Ferries, United Kingdom',
        ],
        'DVR' => [
            'prefix' => 'GB',
            'name'   => 'Dover Port P&O Ferries, United Kingdom',
        ],
        'DOV' => [
            'prefix' => 'GB',
            'name'   => 'Dover Port P&O Ferries, United Kingdom',
        ],
        'CQF' => [
            'prefix' => 'FR',
            'name'   => 'Calais Port P&O Ferries, France',
        ],
        'CAL' => [
            'prefix' => 'FR',
            'name'   => 'Calais Port P&O Ferries, France',
        ],
        'RTM' => [
            'prefix' => 'FR',
            'name'   => 'Europoort Port P&O Ferries, Netherlands', // Rotterdam
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.poferries.com')] | //img[contains(@src,'.poferries.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers["subject"]) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
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
        $f = $email->add()->ferry();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Booking Number:'))}]",
                null, true, "/:\s*(\d{5,})\s*$/"))
            ->travellers(array_unique($this->http->FindNodes("//text()[" . $this->starts("Passengers ") . "]/following::text()[normalize-space()][1]", null, "/^\s*(?:(?:mr|ms|dr) )?(.+?)\s*(\(|$)/")), true)
        ;

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total (Inc Vat)'))}]/ancestor::td[1]/following-sibling::*[normalize-space()!=''][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $f->price()
                ->total(PriceHelper::cost($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
        $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('SubTotal'))}]/ancestor::td[1]/following-sibling::*[normalize-space()!=''][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $cost, $m)) {
            $f->price()
                ->cost(PriceHelper::cost($m['amount']))
            ;
        }
        $discount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Discount'))}]/ancestor::td[1]/following-sibling::*[normalize-space()!=''][1]");

        if (preg_match("#^\s*[\w ]*-\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $discount, $m)
            || preg_match("#^\s*[\w ]*-\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $discount, $m)) {
            $f->price()
                ->discount(PriceHelper::cost($m['amount']))
            ;
        }
        $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vat'))}]/ancestor::td[1]/following-sibling::*[normalize-space()!=''][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $tax, $m)) {
            $f->price()
                ->tax(PriceHelper::cost($m['amount']))
            ;
        }

        $fees = $this->http->XPath->query("//tr[not(.//tr)][*[1][{$this->eq($this->t('feesNames'))}]]");

        foreach ($fees as $fRoot) {
            $name = $this->http->FindSingleNode("*[1]", $fRoot);
            $value = $this->http->FindSingleNode("*[2]", $fRoot);

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $value, $m)) {
                $f->price()
                    ->fee($name, PriceHelper::cost($m['amount']));
            }
        }

        $xpath = "//text()[{$this->eq($this->t('Depart time:'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1][.//img and count(*[normalize-space()]) = 2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            $block = "ancestor::*[contains(.,'Base Price')][1]";

            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $root, true, "/.*\b\d{4}\b.*/");

            // Departure
            $time = $this->http->FindSingleNode("following::text()[normalize-space()][position()<8][{$this->eq($this->t('Depart time:'))}]/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d{1,2}:\d{2})\s*$/");
            $code = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^\s*([A-Z]{3})\s*$/");
            $depdate = strtotime((!empty($date) && !empty($time)) ? $date . ', ' . $time : null);
            $s->departure()
                ->code(($this->ports[$code] ? $this->ports[$code]['prefix'] : '') . $code)
                ->name($this->ports[$code] ? $this->ports[$code]['name'] : '')
                ->date($depdate)
            ;

            // Arrival
            $time = $this->http->FindSingleNode("following::text()[normalize-space()][position()<8][{$this->eq($this->t('Arrival time:'))}]/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d{1,2}:\d{2})\s*$/");
            $code = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/^\s*([A-Z]{3})\s*$/");
            $arrdate = strtotime((!empty($date) && !empty($time)) ? $date . ', ' . $time : null);

            if (!empty($arrdate) && !empty($depdate) && $arrdate < $depdate) {
                $arrdate = strtotime('+1 day', $arrdate);
            }
            $s->arrival()
                ->code(($this->ports[$code] ? $this->ports[$code]['prefix'] : '') . $code)
                ->name($this->ports[$code] ? $this->ports[$code]['name'] : '')
                ->date($arrdate)
            ;

            if ($nodes->length == 1) {
                $pets = $this->http->FindNodes("//text()[" . $this->starts($this->t("Pets ")) . "]",
                    null, "/^\s*" . $this->opt($this->t("Pets ")) . "(\d+)\s*$/");

                if (!empty($pets)) {
                    $s->booked()
                        ->pets(max($pets));
                }

                $vehicles = $this->http->FindNodes("//text()[" . $this->starts($this->t("Vehicles :")) . "]/ancestor::td[1]",
                    null, "/^\s*" . $this->opt($this->t("Vehicles : ")) . "\s*(.+)\s*$/");

                foreach ($vehicles as $vehicle) {
                    $s->addVehicle()
                        ->setType($vehicle);
                }
            } else {
                $type = $this->http->FindSingleNode("preceding::text()[normalize-space()][2]", $root);

                $vehicles = $this->http->FindNodes("//text()[" . $this->eq($this->t("Vehicle Details")) . "]/following::text()[" . $this->eq($type) . "][1]/following::text()[normalize-space()][1][" . $this->starts($this->t("Vehicles :")) . "]/ancestor::td[1]",
                    null, "/^\s*" . $this->opt($this->t("Vehicles : ")) . "\s*(.+)\s*$/");

                foreach ($vehicles as $vehicle) {
                    $s->addVehicle()
                        ->setType($vehicle);
                }
            }

            $childs = 0;
            $adults = 0;
            $dopInfos = $this->http->FindNodes("ancestor::*[{$this->contains($this->t('Base Price'))} and count(.//text()[{$this->eq($this->t('Depart time:'))}]) = 1][1]//tr[{$this->contains($this->t('Arrival time:'))}]/following::tr[not(.//tr) and count(.//text()[normalize-space()]) = 2]", $root);

            foreach ($dopInfos as $row) {
                if (stripos($row, 'Base Price') !== false || stripos($row, 'Add-Ons') !== false) {
                    break;
                }

                if (preg_match("/^\s*\d+ x /", $row)) {
                    continue;
                }

                if (preg_match("/^\s*(?:Senior Citizen|Adult)[^:]*:\s*(?<count>\d{1,2})\s*$/", $row, $m)) {
                    $adults += $m['count'];
                } elseif (preg_match("/^\s*(?:Child|Infant)[^:]*:\s*(?<count>\d{1,2})\s*$/", $row, $m)) {
                    $childs += $m['count'];
                } elseif (preg_match("/^\s*\D+\b(\d{1,2})\b(?:[^:\d]*\d{1,2})?[^:\d]*:\s*(?<count>\d{1,2})\s*$/", $row, $m)) {
                    if ($m[1] <= 18) {
                        $childs += $m['count'];
                    } else {
                        $adults += $m['count'];
                    }
                }
            }

            if (!empty($childs)) {
                $s->booked()
                    ->kids($childs);
            }

            if (!empty($adults)) {
                $s->booked()
                    ->adults($adults);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
