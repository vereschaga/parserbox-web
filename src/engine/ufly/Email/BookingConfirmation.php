<?php

namespace AwardWallet\Engine\ufly\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "ufly/it-158720338.eml, ufly/it-160979297.eml, ufly/it-162083930.eml";

    public $detectFrom = 'suncountry@info.suncountry.com';
    public $detectSubject = [
        // en
        "Booking Confirmation",
        "An important message regarding flight",
        "Important information about your flight",
    ];

    /*
    public $detectBody = [
        'en'=> [
            'Below is your itinerary and receipt', "Your full trip details",
            'Flight Itinerary', 'you requested notifications for',
            'review the updated flight information below',
        ],
    ];
    */

    public $lang = 'en';
    public static $dictionary = [
        "en" => [
            "reservationCode" => ["Reservation code:", "Reservation Code:"],
            "departs"         => ["DEPARTS"],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('BookingConfirmation' . ucfirst($this->lang));
        $this->parseHtml($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['subject']) || empty($headers['from'])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".suncountry.com/") or contains(@href,"www.suncountry.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Sun Country Airlines, All rights reserved")]')->length === 0
        ) {
            return false;
        }

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

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t("reservationCode"))}]/following::text()[normalize-space()][1]", null, true,
            "/^\s*([A-Z\d]{5,7})\s*$/");

        if (empty($conf) && !empty($this->http->FindSingleNode("(//text()[" . $this->contains(['you requested notifications for']) . "])[1]"))) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($conf);
        }

        $travellers = array_filter($this->http->FindNodes("//img[contains(@src,'pax-icon_')]/following::text()[normalize-space()][1]/ancestor::h3", null, "/^{$patterns['travellerName']}$/u"));

        if (count($travellers) > 0) {
            // it-162083930.eml
            $f->general()->travellers(array_unique($travellers), true);
        } else {
            // it-158720338.eml
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));
            $travellerNames = array_filter(preg_replace("/^\s*Travell?er\s*$/i", '', $travellerNames));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $f->general()->traveller($traveller);
            }
        }

        $date = strtotime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Date:")) . "]/following::text()[normalize-space()][1]"));

        if (!empty($date)) {
            $f->general()
                ->date($date);
        }

        // Program
        $accounts = array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Sun Country Rewards")) . "]",
            null, "/Sun Country Rewards (\d{5,})\s*$/"));

        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }

        // Segments
        $xpath = "//text()[normalize-space() = 'DEPARTS'][not(preceding::text()[" . $this->eq(['Original Flight', 'ORIGINAL FLIGHT']) . "])]/ancestor::*[count(.//text()[normalize-space() = 'DEPARTS']) = 1 and following-sibling::*[normalize-space()][1][contains(., '(') and not(contains(., 'STOPS'))]][1]";
        $segments = $this->http->XPath->query($xpath);
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $stops = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("STOPS")) . "]/following::text()[normalize-space()][1]", $segment, null, "/^\s*(\d+)\D*$/");

            if ($stops > 1) {
                $this->logger->debug("too much stops");

                return;
            }

            $additionalAirline = null;

            // Airline
            $flight = $this->http->FindSingleNode('descendant::td[not(.//td)][normalize-space()][1]', $segment);

            if (preg_match('/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,5}(\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,5})*\s*$/', $flight, $m)) {
                $airlines = explode(" ", trim($flight));

                if (count($airlines) == $stops + 1) {
                    if (preg_match('/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,5})\s*$/', $airlines[0] ?? '', $m)) {
                        $s->airline()
                            ->name($m['name'])
                            ->number($m['number']);
                    }

                    if (preg_match('/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,5})\s*$/', $airlines[1] ?? '', $m)) {
                        $additionalAirline = [
                            'name'   => $m['name'],
                            'number' => $m['number'],
                        ];
                    }
                }
            }

            $route = "following-sibling::*[normalize-space()][1]/descendant::*[count(*[normalize-space()]) = 2 and count(*[normalize-space()][1][contains(., ')')]) = 1 and count(*[normalize-space()][2][contains(., ')')]) = 1][1]/";
            $regex = "/^\s*(?<date>.+?\s+\d{1,2}:\d{2}(?:\s*[apAP][mM])?)\s+(?<overnight>[-+] *\d\s+)?(?<name>[A-Z].+)?\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*(?:\((?<terminal>[\w ]+)\))?\s*$/";

            // Departure
            $departure = implode(" ", $this->http->FindNodes($route . "*[1]//text()[normalize-space()]", $segment));

            if (preg_match($regex, $departure, $m)) {
                if (!empty($m['name'])) {
                    if (preg_match("/(.+?)\(\s*([^()]*\bterminal\b[^()]*)\)\s*$/i", $m['name'], $mat)) {
                        $m['name'] = $mat[1];
                        $m['terminal'] = preg_replace("/\s*\bterminal\b\s*/i", ' ', $mat[2]);
                    }

                    $s->departure()->name($m['name']);
                }

                $s->departure()->date($this->normalizeDate($m['date']))->code($m['code']);

                if (!empty($m['terminal'])) {
                    $s->departure()->terminal($m['terminal']);
                }

                if (!empty($m['overnight']) && !empty($s->getDepDate())) {
                    $s->departure()
                        ->date(strtotime(trim($m['overnight']) . " day", $s->getDepDate()))
                    ;
                }
            } elseif (preg_match("/^\s*(?<name>\D*)\s*\((?<code>[A-Z]{3})\)\s*(?:\((?<terminal>[\w ]+)\))?\s*$/", $departure, $m)) {
                if (preg_match("/(.+?)\(\s*([^()]*\bterminal\b[^()]*)\)\s*$/i", $m['name'], $mat)) {
                    $m['name'] = $mat[1];
                    $m['terminal'] = preg_replace("/\s*\bterminal\b\s*/i", ' ', $mat[2]);
                }
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->noDate()
                    ->terminal($m['terminal'] ?? null, true, true)
                ;
            }

            if ($stops == 1) {
                $stopCode = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("STOPS")) . "]/following::text()[normalize-space()][1]", $segment, null, "/^\s*\d+\s*\(([A-Z]{3})\)\s*$/");
                $s->arrival()
                    ->code($stopCode)
                    ->noDate()
                ;
                // Extra
                $routeName = '(' . $s->getDepCode() . '-' . $s->getArrCode() . ')';

                if (strlen($routeName) == 9) {
                    $seats = array_filter($this->http->FindNodes("//text()[normalize-space()='{$routeName}']/following::text()[normalize-space()][1][" . $this->eq($this->t('Seat')) . "]/following::text()[normalize-space()][1]",
                        $segment, "/^\s*(\d{1,3}[A-Z])\s*$/"));

                    if (!empty($seats)) {
                        $s->extra()
                            ->seats($seats);
                    }
                }

                $s = $f->addSegment();
                $s->departure()
                    ->code($stopCode)
                    ->noDate()
                ;

                $s->airline()
                    ->name($additionalAirline['name'] ?? null)
                    ->number($additionalAirline['number'] ?? null);
            }

            // Arrival
            $arrival = implode(" ", $this->http->FindNodes($route . "*[2]//text()[normalize-space()]", $segment));

            if (preg_match($regex, $arrival, $m)) {
                if (!empty($m['name'])) {
                    if (preg_match("/(.+?)\(\s*([^()]*\bterminal\b[^()]*)\)\s*$/i", $m['name'], $mat)) {
                        $m['name'] = $mat[1];
                        $m['terminal'] = preg_replace("/\s*\bterminal\b\s*/i", ' ', $mat[2]);
                    }

                    $s->arrival()->name($m['name']);
                }

                $s->arrival()->date($this->normalizeDate($m['date']))->code($m['code']);

                if (!empty($m['terminal'])) {
                    $s->arrival()->terminal($m['terminal']);
                }

                if (!empty($m['overnight']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($m['overnight'] . "day", $s->getArrDate()))
                    ;
                }
            }

            // Extra
            $routeName = '(' . $s->getDepCode() . '-' . $s->getArrCode() . ')';

            if (strlen($routeName) == 9) {
                $seats = array_filter($this->http->FindNodes("//text()[normalize-space()='{$routeName}']/following::text()[normalize-space()][1][" . $this->eq($this->t('Seat')) . "]/following::text()[normalize-space()][1]",
                    $segment, "/^\s*(\d{1,3}[A-Z])\s*$/"));

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }
        }

        // Total
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Total:")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("/^\s*(?<currency>[^\-\d)(]{1,5}?)\s*(?<amount>\d[\d\., ]*?)\s*$/", $total, $matches)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*?)\s*(?<currency>[^\-\d)(]{1,5}?)\s*$/", $total, $matches)
        ) {
            // $ 1,544.80
            $currency = $this->currency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['reservationCode']) || empty($phrases['departs'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['reservationCode'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($phrases['departs'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //Monday, September 7, 2020 ;
            "/^\s*[^\d\s]+,\s*([^\d\s]+)\s+(\d+)\s*,\s+(\d{4})\s*$/",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s): ?string
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'   => 'EUR',
            '$'   => 'USD',
            'US$' => 'USD',
            '£'   => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
