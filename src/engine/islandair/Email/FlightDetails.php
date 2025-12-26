<?php

namespace AwardWallet\Engine\islandair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightDetails extends \TAccountChecker
{
    public $mailFiles = "islandair/it-175498767.eml, islandair/it-196906990.eml, islandair/it-74001683.eml, islandair/it-775174274.eml";
    public static $dictionary = [
        'en' => [
            'Booking Reference:' => 'Booking Reference:',
            'Taxes and Charges'  => ['Taxes and Charges', 'Federal Excise Tax US'],
            'fees'               => ['9/11 Security Fee AY', 'Passenger Facility Charge XF'],
        ],
    ];

    private $detectSubject = [
        'Island Air Online - Travel Confirmation',
        'Thank you for booking with us, here are your flight details, your itinerary follows',
    ];

    private static $detectProvider = [
        'islandair' => [
            'from' => '@islandair.com',
            'text' => [
                'Mahalo for flying Island Air',
                'Mahalo for flying with Island Air',
            ],
            //            'link' => '',
        ],
        'una' => [
            'from' => '@flyunitednigeria.com',
            'text' => [
                'Any act of assault on crew and staff of United Nigeria',
            ],
            'link' => ['flyunitednigeria.com'],
        ],
        'loganair' => [
            'from' => '@mayaislandair.com',
            'text' => [
                'Maya Island Air is not liable for fragile',
            ],
            //            'link' => '',
        ],
        [
            'from' => '@denverairconnection.com',
            'text' => [
                '/denverairconnection.com',
            ],
            'link' => 'denverairconnection.com',
        ],
        [
            'from' => '@flyezair.com',
            'text' => [
                'Visit EZAir\'s website at ',
            ],
            'link' => 'www.flyezair.com',
        ],
        [
            'from' => '@seaborneairlines.com',
            'text' => [
                'Seaborne Airlines wants to thank you for flying with us',
            ],
            //            'link' => '',
        ],
        [
            'from' => '@ibomair.com',
            //            'text' => [],
            //            'link' => '',
        ],
        'videcom' => [
            'from' => '@videcom.com',
            'text' => ['@videcom.com'],
            //            'link' => '',
            'isTravelAgency' => true,
        ],
        'intercaribair' => [
            'from'           => '@intercaribbean.com',
            'text'           => ['@intercaribbean.com', 'choosing interCaribbean Airways'],
            'link'           => 'intercaribbean.com',
            'isTravelAgency' => false,
        ],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Booking Reference:']) && $this->http->XPath->query("//*[" . $this->contains($dict['Booking Reference:']) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

        $providerCode = null;

        foreach (self::$detectProvider as $code => $prov) {
            if (!empty($prov['from']) && stripos($parser->getHeaders()['from'], $prov['from']) !== false) {
                $providerCode = $code;

                break;
            }
        }

        if (empty($providerCode)) {
            foreach (self::$detectProvider as $code => $prov) {
                if ((!empty($prov['text']) && $this->http->XPath->query("//*[" . $this->contains($prov['text']) . "]")->length > 0)
                    || (!empty($prov['link']) && $this->http->XPath->query("//a[" . $this->contains($prov['link'], '@href') . "]")->length > 0)) {
                    $providerCode = $code;

                    break;
                }
            }
        }

        if (!empty($providerCode) && !is_numeric($providerCode)) {
            $email->setProviderCode($providerCode);

            if (isset(self::$detectProvider[$providerCode]) && !empty(self::$detectProvider[$providerCode]['isTravelAgency'])) {
                $email->obtainTravelAgency();
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, '@islandair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        $detectedProvider = false;

        foreach (self::$detectProvider as $prov) {
            if (!empty($prov['from']) && stripos($headers['from'], $prov['from']) !== false) {
                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider == false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//tr["
                . "td[normalize-space()][1][" . $this->eq("Date") . "] and "
                . "td[normalize-space()][3][" . $this->eq("All Times Local") . "] and "
                . "td[normalize-space()][4][" . $this->eq("Flight-Number") . "] and "
                . "td[normalize-space()][5][" . $this->eq("Cabin (Book Class)") . "]"
                . "]")->length > 0) {
            return true;
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

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$detectProvider), function ($v) {
            return (is_numeric($v)) ? false : true;
        });
    }

    private function parseHtml(Email $email): void
    {
        $f = $email->add()->flight();

        // General

        $conf = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Booking Reference:")) . "]/following::text()[normalize-space()][1]", null, "/^\s*([A-Z\d]{5,7})\s*/"));

        if (empty($conf)) {
            $conf = array_unique($this->http->FindNodes("//text()[(normalize-space(.)='DEPARTING:')]/preceding::text()[normalize-space()='Booking Reference:'][1]/following::text()[normalize-space()][1]", null, "/^\s*([A-Z\d]{5,7})\s*/"));
        }

        $confDescription = $this->http->FindNodes("//text()[" . $this->eq($this->t("Booking Reference:")) . "]");

        if (count($confDescription) == 0) {
            $confDescription = $this->http->FindNodes("//text()[(normalize-space(.)='DEPARTING:')]/preceding::text()[normalize-space()='Booking Reference:'][1]");
        }

        foreach ($conf as $key => $confNumber) {
            $f->general()
                ->confirmation($conf[$key], trim($confDescription[$key], ':'));
        }

        $f->general()
            ->travellers(preg_replace(["/\(\S+\)/", "/^\s*(MR|MISS|MS|MSTR|MRS) /"], "", $this->http->FindNodes("//td[" . $this->eq(preg_replace('/\s+/', '', $this->t("PASSENGER INFORMATION")), "translate(normalize-space(), ' ', '')") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[2]//td[not(.//td)][normalize-space()]")), true)
        ;

        // Issied
        $f->issued()
            ->tickets($this->http->FindNodes("//td[" . $this->eq($this->t("FLIGHT TICKET NUMBERS")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[3]//text()[normalize-space()]", null, "/^(?:ETKT|ELFT)(.+)/"), false);

        // Price
        $cost = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Price ")) . "]", null, true, "/Price (.+)/");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $cost, $m)) {
            $f->price()
                ->cost($this->amount($m['amount']))
            ;
        }
        $tax = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Taxes and Charges")) . "]", null, true, "/{$this->opt($this->t('Taxes and Charges'))}(.+)/");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $tax, $m)) {
            $f->price()
                ->tax($this->amount($m['amount']))
            ;
        }

        $fees = $this->http->FindNodes("//text()[{$this->starts($this->t('fees'))}]");

        foreach ($fees as $fee) {
            if (preg_match("/^(?<name>.+)\s+(?<curr>[^\d\s]{1,})\s*(?<summ>\d[\d\., ]*)\s*$/", $fee, $m)) {
                $f->price()->fee($m['name'], $m['summ']);
            }
        }

        $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total Price")) . "]", null, true, "/Total Price (.+)/");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        // Segments
        $xpath = "//td[" . $this->eq($this->t("From:")) . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
//        $this->logger->debug($xpath);
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./preceding::tr[td[1][" . $this->eq($this->t("Date")) . "]][1]/td[2]", $root);

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./td[5]", $root, true, "/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d{1,5}\s*(?:\(|$)/"))
                ->number($this->http->FindSingleNode("./td[5]", $root, true, "/^\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*(?:\(|$)/"))
            ;

            // Departure
            $s->departure()
                ->name($this->http->FindSingleNode("./td[2]", $root))
                ->noCode()
                ->date($this->normalizeDate($date . ', ' . $this->http->FindSingleNode("./td[4]", $root)))
            ;

            // Arrival
            $s->arrival()
                ->name($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][1]/td[2]", $root))
                ->noCode()
                ->date($this->normalizeDate($date . ', ' . $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][1]/td[4]",
                        $root, null, "/^\s*(.+?)(?:\(.*)?$/")))
            ;

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode("./td[6]", $root, true, "/^\s*(.+)\s*\([A-Z]{1,2}\)\s*$/"))
                ->bookingCode($this->http->FindSingleNode("./td[6]", $root, true, "/^\s*.+\s*\(([A-Z]{1,2})\)\s*$/"))
            ;

            $infoText = $this->http->XPath->query("following-sibling::tr[normalize-space()][1]/following::text()[normalize-space()][1][{$this->starts($this->t('Info Flight'))}]/following::text()[normalize-space()][position() < 10]", $root);

            foreach ($infoText as $iRoot) {
                if (preg_match("/^\s*{$this->opt($this->t('Rules Flight'))}/", $iRoot->nodeValue)) {
                    break;
                }

                if (preg_match("/^\s*(?<name>.+?) {$this->opt($this->t('SEAT'))} (?<dCode>[A-Z]{3})(?<aCode>[A-Z]{3}) (?<seat>\d{1,3}[A-Z])\s*$/", $iRoot->nodeValue, $m)) {
                    $s->extra()
                        ->seat($m['seat'], true, true, preg_replace(["/\(\S+\)/", "/^\s*(MR|MISS|MS|MSTR|MRS) /"], "", $m['name']));

                    $s->setNoDepCode(false);
                    $s->departure()->code($m['dCode']);
                    $s->setNoArrCode(false);
                    $s->arrival()->code($m['aCode']);
                }
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Mon 28 Dec 20, 12:00
            "/^\s*[[:alpha:]]+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{2})\s*,\s*(\d{1,2}:\d{2})\s*$/iu",
        ];
        $out = [
            "$1 $2 20$3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
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

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
