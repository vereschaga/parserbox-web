<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: add status

class ETicket2023 extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-637542779.eml";

    public static $detectProvider = [
        'goibibo' => [
            'from'   => ['noreply@goibibo.com'],
            'link'   => ['goibibo.com', 'go.ibi.bo/'],
            'imgAlt' => [], // =
            'imgSrc' => ['goibibo-logo.png'], // contains
            'text'   => ['Goibibo'],
        ],
        'maketrip' => [
            'from'   => ['@makemytrip.com', 'MakeMyTrip'],
            'link'   => '.makemytrip.com',
            'imgAlt' => ['mmt_logo', 'MakeMyTrip'], // =
            'imgSrc' => ['.mmtcdn.com'], // contains
            'text'   => ['MakeMyTrip'],
        ],
    ];

    public $detectSubject = [
        'E-Ticket for Your Flight Booking ID',
    ];

    public $detectBody = [
        'en' => ['BOOKING DETAILS', 'Your trip details'],
    ];

    public $date;

    public static $dictionary = [
        'en' => [
            'cabinValues' => ['Economy Class', 'Economy', 'Business'],
        ],
    ];

    private $providerCode = '';
    private $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $this->assignProvider();
        $this->assignLang();

        $this->parseEmail($email);

        // Price
        $total = $this->getTotal($this->http->FindSingleNode("//td[normalize-space(.)='Total Price']/following-sibling::td[normalize-space(.)][1]"));

        if ($total['amount'] !== null) {
            // ₹ 6132
            $email->price()->currency($total['currency'])->total($total['amount']);
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->assignProvider() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $code => $providerParams) {
            if ($this->striposAll($headers["from"], $providerParams['from']) === false) {
                continue;
            }
            $this->providerCode = $code;

            foreach ($this->detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'MakeMyTrip') !== false;
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
        return array_keys(self::$detectProvider);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email): void
    {
        $xpathAirportCode = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"';
        $xpathTime = 'contains(translate(.,"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        // Travel Agency
        $tripNum = $this->http->FindSingleNode("//text()[contains(.,'Booking ID')]/ancestor::td[1]", null, true, "#.+?:\s+([A-Z\d\-]+)#");

        if (empty($tripNum)) {
            $tripNum = $this->http->FindSingleNode("//tr[contains(., 'Booked ID') and not(.//tr)]/following-sibling::tr[1]/td[2]");
        }
        $email->ota()
            ->confirmation($tripNum);

        $xpath = "//tr[ count(*)=3 and *[1][{$xpathTime}]/descendant::text()[{$xpathAirportCode}] and *[2][not(descendant::text()[{$xpathAirportCode}])] and *[3][{$xpathTime}]/descendant::text()[{$xpathAirportCode}] ]/ancestor::tr[count(*[normalize-space()])=2][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug('Segments nof found by xpath: ' . $xpath);

            return;
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
        ;
        $travellers = $this->http->FindNodes("//tr[*[1][normalize-space()='TRAVELLER']]/following-sibling::tr[normalize-space()]/*[1][not(starts-with(normalize-space(), 'Wheelchair request '))]");

        $travellers = preg_replace("/^\s*(Mr|Ms|Miss|Mrs|Dr|Mstr)\. +/i", '', $travellers);
        $travellers = preg_replace("/^\s*(.+?)\s* (?:Adult|Child|Infant|\().*$/i", '$1', $travellers);
        $travellers = array_unique($travellers);
        $f->general()
            ->travellers($travellers, true)
        ;
        $ticketNumbers = array_unique(array_filter($this->http->FindNodes("//tr[*[1][normalize-space()='TRAVELLER']]/following-sibling::tr/*[4]",
            null, "/.*\d{8,}.*/")));

        if (!empty($ticketNumbers)) {
            $f->issued()
                ->tickets(array_unique($ticketNumbers), false);
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $segmentDate = $this->normalizeDate($this->http->FindSingleNode("./preceding::tr[not(.//tr)][contains(., ' duration')][1]",
                $root, null, "/^(.+?)\s*•/u"));

            // Airline
            $node = implode(" ", $this->http->FindNodes("./td[1]//text()[normalize-space(.)]", $root));

            if (preg_match("#\s*([A-Z\d]{2})\s*-\s*(\d+)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            $root2Nodes = $this->http->XPath->query("descendant::tr[count(*)=3][1]", $root);

            if ($root2Nodes->length === 0) {
                continue;
            }
            $root2 = $root2Nodes->item(0);

            $patternNT = "/^(?<name>[\s\S]+?)\s+(?:Terminal[-–\s]+)+(?<terminal>\S[\s\S]*?)?\s*$/i";

            // Departure
            $departureText = implode("\n", $this->http->FindNodes("./td[1]/descendant::text()[normalize-space(.)]", $root2));

            if (preg_match("/^(?<city>[\s\S]+)\n\s*(?<code>[A-Z]{3})\s+(?<time>\d{1,2}:\d{2}) hrs\s*\n(?<date>.+)\n(?<airport>[\s\S]+)$/", $departureText, $m)) {
                $m = array_map('trim', preg_replace('/\s+/', ' ', $m));

                if (preg_match($patternNT, $m['airport'], $m2)) {
                    $m['airport'] = $m2['name'];
                    $s->departure()->terminal($m2['terminal']);
                }

                $s->departure()
                    ->code($m['code'])
                    ->name(trim($m['city']) . ', ' . ($m['airport']))
                    ->date(strtotime($m['time'], $this->normalizeDateRelative($m['date'], $segmentDate)))
                ;
            }

            // Arrival
            $arrivalText = implode("\n", $this->http->FindNodes("./td[3]/descendant::text()[normalize-space(.)]", $root2));

            if (preg_match("/^(?<city>[\s\S]+)\n\s*(?<time>\d{1,2}:\d{2}) hrs\s+(?<code>[A-Z]{3})\s*\n(?<date>.+)\n*(?<airport>[\s\S]+)?$/", $arrivalText, $m)) {
                $m = array_map('trim', preg_replace('/\s+/', ' ', $m));

                if (preg_match($patternNT, $m['airport'], $m2)) {
                    $m['airport'] = $m2['name'];
                    $s->arrival()->terminal($m2['terminal']);
                }

                if (isset($m['city']) && isset($m['airport'])) {
                    $s->arrival()
                        ->name(trim($m['city']) . ', ' . ($m['airport']));
                }

                $s->arrival()
                    ->code($m['code'])
                    ->date(strtotime($m['time'], $this->normalizeDateRelative($m['date'], $segmentDate)));
            }

            // Extra
            $td2 = $this->http->FindSingleNode("*[2]/descendant::text()[normalize-space()][1]", $root2);

            if (preg_match("/^(\d[hrmin\d\s]*?)(?:\s+:|$)/i", $td2, $m)) {
                $s->extra()->duration($m[1]);
            }

            if (preg_match("/(?:^|:\s+)(\d{1,3})\s*stop/i", $td2, $m)) {
                $s->extra()->stops($m[1]);
            }

            $cabinValues = $this->http->FindNodes("ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]/descendant::*[ count(*)=2 and count(*[normalize-space()])=1 and *[1]/descendant::img and not(*[2]/descendant::img)]/*[2][{$this->eq($this->t('cabinValues'))}]", $root);

            if (count(array_unique($cabinValues)) === 1) {
                $cabin = array_shift($cabinValues);
                $s->extra()->cabin($cabin);
            }

            $seats = array_filter($this->http->FindNodes("./ancestor::*[contains(., 'TRAVELLER')][1]/descendant::tr[*[1][normalize-space()='TRAVELLER']][1]/following-sibling::tr/*[2]",
                $root, "/^\s*(\d{1,3}[A-Z])\s*$/"));

            if (!empty($seats)) {
                foreach ($seats as $seat) {
                    $pax = $this->http->FindSingleNode("./ancestor::*[contains(., 'TRAVELLER')][1]/descendant::tr[*[1][normalize-space()='TRAVELLER']][1]/following-sibling::tr/*[2][{$this->contains($seat)}]/ancestor::tr[1]/descendant::text()[normalize-space()][1]",
                        $root);

                    if (!empty($pax)) {
                        $s->extra()
                            ->seat($seat, true, true, preg_replace("/^\s*(Mr|Ms|Miss|Mrs|Dr|Mstr)\. +/i", '', $pax));
                    } else {
                        $s->extra()
                            ->seat($seat);
                    }
                }
            }

            $segPNR = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='PNR'] ]/*[normalize-space()][2]", $root, true, '/^[A-Z\d]{5,7}$/');

            if ($segPNR) {
                $s->airline()->confirmation($segPNR);
            } else {
                $eTicketValues = $this->http->FindNodes("ancestor::*[contains(.,'TRAVELLER')][1]/descendant::tr[ *[1][normalize-space()='TRAVELLER'] ][1]/following-sibling::tr/*[4][normalize-space()]", $root);

                if (count(array_unique($eTicketValues)) === 1 && preg_match('/^[A-Z\d]{5,7}$/', $eTicketValues[0])) {
                    $s->airline()->confirmation($eTicketValues[0]);
                }
            }
        }
    }

    private function normalizeDateRelative($date, $relativeDate)
    {
        if (empty($relativeDate)) {
            return null;
        }
        $year = date('Y', $relativeDate);
        $in = [
            // Sun, Apr 09
            '#^\s*([[:alpha:]]+),\s*(\d+)\s+([[:alpha:]]+)\s*$#iu',
        ];
        $out = [
            '$1, $2 $3 ' . $year,
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>[[:alpha:]]+), (?<date>\d+ [[:alpha:]]+ \d{4})\s*$#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } else {
            $date = null;
        }

        return $date;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('normalizeDate = '.print_r( $date,true));
        $in = [
            // Mon, 20 Mar 2023
            "#^\s*[^\s\d]+,\s+(\d+\s+[^\s\d]+\s+\d{4})\s*$#",
        ];
        $out = [
            "$1",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('normalizeDate 2 = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^\s*\d+\s+([[:alpha:]]+)\s+\d{4}$#", $date, $m)) {
            return strtotime($date);
        }

        return null;
    }

    private function assignProvider(): bool
    {
        foreach (self::$detectProvider as $code => $providerParams) {
            if ($this->http->XPath->query("//img[" . $this->eq($providerParams['imgAlt'], '@alt') . " or " . $this->contains($providerParams['imgSrc'], '@src') . "]")->length > 0
                || $this->http->XPath->query("//a[" . $this->contains($providerParams['link'], '@href') . "]")->length > 0
            ) {
                $this->providerCode = $code;

                return true;
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function getTotal($text): array
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        $sym = [
            '₹'  => 'INR',
            'Rs.'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function eq($field, string $node = 'normalize-space()'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return $node . ' = "' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, string $node = 'normalize-space()'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(' . $node . ', "' . $s . '")';
        }, $field)) . ')';
    }
}
