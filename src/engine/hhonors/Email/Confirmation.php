<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-11.eml, hhonors/it-2003485.eml, hhonors/it-2142019.eml, hhonors/it-2269259.eml, hhonors/it-2269280.eml, hhonors/it-2342694.eml, hhonors/it-7.eml, hhonors/it-8.eml, hhonors/it-9.eml";

    private $lang = '';
    private $detectFrom = [
        '.hilton.com',
        '@hilton.com',
    ];

    private $detectSubject = [
        // en
        'Confirmation #',
        // de
        'Reservierung #',
    ];

    private static $dictionary = [
        'en' => [
            //            'Confirmation:' => '',
            'Thank you for booking with us, ' => 'Thank you for booking with us, ',
            //            'Rate Rules and Cancellation Policy:' => '',
            //            'Rooms & Suites' => '',
            //            'Arrival:' => '',
            //            'Departure:' => '',
            //            'Clients:' => '',
            //            'Adult' => '',
            //            'Rooms:' => '',
            //            'Room Type:' => '',
            //            'Total for Stay:' => '',
            //            'Total Number of Points per Stay:' => '',
        ],
        'de' => [
            'Confirmation:'                       => 'Bestätigung:',
            'Thank you for booking with us, '     => 'Vielen Dank, dass Sie bei uns gebucht haben, ',
            'Rate Rules and Cancellation Policy:' => 'Preisregelungen und Stornierungsrichtlinien:',
            'Rooms & Suites'                      => 'Zimmer & Suiten',
            'Arrival:'                            => 'Tag der Anreise:',
            'Departure:'                          => 'Tag der Abreise:',
            'Clients:'                            => 'Gäste:',
            'Adult'                               => 'Erwachsene',
            'Rooms:'                              => 'Zimmeranzahl:',
            'Room Type:'                          => 'Zimmerkategorie:',
            'Total for Stay:'                     => 'Gesamtrate für alle Zimmer:',
            //            'Total Number of Points per Stay:' => '',
        ],
    ];

    public function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation:'))}]/following::text()[normalize-space()][1]", null, false,
            "/^\s*([\w\-]+)$/u");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation:'))}]", null, false,
                "/{$this->opt($this->t('Confirmation:'))}\s*([\w\-]+)$/u");
        }
        $h->general()
            ->confirmation($conf)
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for booking with us, '))}]", null, false,
                "/{$this->opt($this->t('Thank you for booking with us, '))}\s*([[:alpha:] \-]+)$/u"))
            ->cancellation($this->http->FindSingleNode("//table[" . $this->eq($this->t("Rate Rules and Cancellation Policy:")) . "]/following-sibling::table[1]"))
        ;

        // Hotel
        $hotelInfo = $this->http->FindNodes("//*[{$this->eq($this->t('Rooms & Suites'))}]/preceding::text()[string-length(normalize-space()) > 2][1]/ancestor::tr[1]/ancestor::*[1]/tr");
//        $this->logger->debug('$hotelInfo = '. print_r($hotelInfo, true));
        /*
            [0] => Embassy Suites Brea - North Orange County
            [1] => 900 East Birch Street | Brea | CA | United States 92821
            [2] => T: 1-714-990-6000 | F: 1-714-662-1651
         */
        if (count($hotelInfo) == 3) {
            if (preg_match("/^\s*T:\s+(.*)\s+\|\s+F:\s+(.*)/", $hotelInfo[2], $m)) {
                $h->hotel()
                    ->name($hotelInfo[0])
                    ->address(preg_replace('/\s*\|[\s\|]*/', ', ', $hotelInfo[1]))
                    ->phone($m[1])
                    ->fax($m[2])
                ;
            }
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Departure:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]")))
            ->guests($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Clients:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1])[1]", null, true, "/^\s*(\d+)\s+" . $this->opt($this->t("Adult")) . "/"))
            ->rooms($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Rooms:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1])[1]", null, true, "/^\s*(\d+)\s*$/"))
        ;

        $h->addRoom()
            ->setType($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Room Type:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1])[1]"))
        ;

        // Total
        $total = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Total for Stay:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1])[1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Number of Points per Stay:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (!empty($total)) {
            $h->price()
                ->spentAwards($total)
            ;
        }

        return $email;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->detectSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang()
    {
        if ($this->http->XPath->query("//a[contains(@href, '.hilton.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Thank you for booking with us, ']) && $this->http->XPath->query("//text()[{$this->contains($dict['Thank you for booking with us, '])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            // 2021-02-26, 00:00
            "#^\s*(\d{4})-(\d{2})-(\d{2}), (\d{2}:\d{2})\s*$#i",
        ];
        $out = [
            "$3.$2.$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
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
