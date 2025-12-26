<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ETicket4Booking2017 extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-10050092.eml, maketrip/it-10057468.eml, maketrip/it-6443993.eml, maketrip/it-6677686.eml, maketrip/it-67512092.eml, maketrip/it-8281444.eml, maketrip/it-8386347.eml, maketrip/it-8567437.eml, maketrip/it-8620618.eml";

    public static $detectProvider = [
        'maketrip' => [
            'from'   => ['@makemytrip.com', 'MakeMyTrip'],
            'link'   => '.makemytrip.com',
            'imgAlt' => ['mmt_logo', 'MakeMyTrip'], // =
            'imgSrc' => ['.mmtcdn.com'], // contains
            'text'   => ['MakeMyTrip'],
        ],
        'goibibo' => [
            'from'   => ['noreply@goibibo.com'],
            'link'   => ['goibibo.com', 'go.ibi.bo/'],
            'imgAlt' => [], // =
            'imgSrc' => ['goibibo-logo.png'], // contains
            'text'   => ['Goibibo'],
        ],
    ];

    public $detectSubject = [
        'E-Ticket for Booking',
        'E-Ticket for Your Flight Booking ID',
        'Customer ETicket',
    ];

    public $detectBody = [
        'en' => ['PASSENGER'],
    ];

    public $date;

    public static $dict = [
        'en' => [],
    ];

    private $providerCode;
    private $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $this->assignLang();

        $this->parseEmail($email);

        $total = $this->http->FindSingleNode("//text()[normalize-space(.)='You have Paid']/following::text()[string-length(normalize-space(.))>3][1]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//td[contains(., 'Grand Total') and not(.//td)]/following-sibling::td[1]");
        }
        $cur = null;
        $arr = explode('/', $this->http->FindSingleNode("//td[contains(., 'Grand Total') and not(.//td)]/following-sibling::td[1]/img/@src", null, true, '/(.+)\.\w+/'));

        if (count($arr) > 0) {
            $currencyInImgSrc = $arr[count($arr) - 1];
            $cur = str_replace(['rupee_red'], ['INR'], $currencyInImgSrc);
        }
        $tot = $this->getTotalCurrency($total);

        if (!empty($tot['Total'])) {
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency'] ?? $cur)
            ;
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
        foreach (self::$detectProvider as $code => $providerParams) {
            $foundProvider = false;

            if ($this->http->XPath->query("//img[" . $this->eq($providerParams['imgAlt'], '@alt') . " or " . $this->contains($providerParams['imgSrc'], '@src') . "]")->length > 0
                || $this->http->XPath->query("//a[" . $this->contains($providerParams['link'], '@href') . "]")->length > 0) {
                $this->providerCode = $code;
                $foundProvider = true;
            }

            if ($foundProvider === false) {
                continue;
            }

            if ($this->assignLang()) {
                return true;
            }
        }

        return false;
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
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
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

    private function parseEmail(Email $email)
    {
        $patterns = [
            'terminal' => '\s*(Terminal[\w\s]*\w|\w[\w\s]*Terminal)',
        ];

        // Travel Agency
        $tripNum = $this->http->FindSingleNode("//text()[contains(.,'Booking ID')]/ancestor::td[1]", null, true, "#.+?:\s+([A-Z\d\-]+)#");

        if (empty($tripNum)) {
            $tripNum = $this->http->FindSingleNode("//tr[contains(., 'Booked ID') and not(.//tr)]/following-sibling::tr[1]/td[2]");
        }
        $email->ota()
            ->confirmation($tripNum);

        $pnrs = array_unique($this->http->FindNodes("//text()[contains(.,'PNR')]/ancestor::tr[contains(.,'SEAT')][1]/following-sibling::tr//table[count(descendant::table)=0][1]//td[3][normalize-space()!='']"));

        if (empty($pnrs) && $this->http->XPath->query("//tr[normalize-space(.) = 'Passengers:']/following-sibling::tr")->length > 0) {
            $pnrs[] = CONFNO_UNKNOWN;
        }

        $flights = [];
        $xpath = "//img[contains(@src,'airline-logos') or contains(@class,'airline-logo') or contains(@src,'drawable-mdpi') or contains(@src,'airlinelogos') or contains(@src,'flightimg') or contains(@src,'/arrow.png')or contains(@src,'/flights/assets/media/dt/common/icons/')]/ancestor::tr[count(./td)=2][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->info('Segments nof found by xpath: ' . $xpath);

            return [];
        }

        foreach ($segments as $root) {
            foreach ($pnrs as $pnr) {
                if ($this->http->XPath->query("./following::text()[contains(.,'PNR')][1]/ancestor::tr[contains(.,'SEAT')][1]/following-sibling::tr[contains(.,'{$pnr}')]", $root)->length > 0) {
                    $flights[$pnr][] = $root;
                } elseif ($this->http->XPath->query("//tr[normalize-space(.) = 'Passengers:']/following-sibling::tr")->length > 0) {
                    $flights[$pnr][] = $root;
                }
            }
        }

        foreach ($pnrs as $pnr) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->confirmation($pnr)
            ;

            $travellers = array_unique($this->http->FindNodes("//text()[contains(.,'{$pnr}')]/ancestor::td[1]/preceding-sibling::td[1]", null, "#(.+?)\s*,#"));

            if (empty($travellers)) {
                $travellers = $this->http->FindNodes("//tr[normalize-space(.) = 'Passengers:']/following-sibling::tr/descendant::td[not(.//td)]", null, '/\d+\.\s*(.+)/');
            }
            $travellers = preg_replace("/^\s*(Mr|Ms|Miss|Mrs|Dr|Mstr)\. +/", '', $travellers);
            $f->general()
                ->travellers($travellers, true)
            ;

            $ticketNumbers = $this->http->FindNodes("//text()[contains(.,'PNR')]/ancestor::tr[contains(.,'SEAT')][1]/following-sibling::tr[contains(.,'{$pnr}')]//table[count(descendant::table)=0][2]//td[1]",
                null, "/.*\d{10,}.*/");
            $ticketNumberValues = array_values(array_filter($ticketNumbers));

            if (!empty($ticketNumberValues[0])) {
                $f->issued()
                    ->tickets(array_unique($ticketNumberValues), false);
            }

            $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";

            if (isset($flights[$pnr])) {
                foreach ($flights[$pnr] as $root) {
                    $s = $f->addSegment();

                    $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::tr[normalize-space()!=''][1]/td[normalize-space()!=''][1]", $root)));

                    if (!empty($date)) {
                        $this->date = $date;
                    }
                    $node = $this->http->FindSingleNode("./preceding::tr[1][contains(.,' TO ')]/td[1]", $root);

                    if (!empty($node)) {
                        $date = strtotime($this->normalizeDate($this->re("#^(.+)\n#", $node)));

                        if (!empty($date)) {
                            $this->date = $date;
                        }
                    }

                    // Airline
                    $node = implode(" ", $this->http->FindNodes("./td[1]//text()[normalize-space(.)]", $root));

                    if (preg_match("#\s*([A-Z\d]{2})\s*-\s*(\d+)#", $node, $m)) {
                        $s->airline()
                            ->name($m[1])
                            ->number($m[2])
                        ;
                    }

                    $root2 = $this->http->XPath->query(".//img[(contains(@src, '/arrow.png')) or (contains(@src, '/watch-icon.png')) or (contains(translate(@style,' ',''),'width:41px') and contains(translate(@style,' ',''),'height:10px')) ]/ancestor::tr[./td[3]][1]", $root)->item(0);

                    // Departure
                    $dateDep = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][position()=3 or position()=4][{$ruleTime}]", $root2);
                    $s->departure()
                        ->code($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root2))
                        ->name($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][2]", $root2))
                        ->date(strtotime($this->normalizeDate($dateDep)))
                    ;
                    $terminalDep = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][not(ancestor::a[contains(@href, '.google.com/maps')])][last()]", $root2, true, "/^\s*(?:.+,)?\s*{$patterns['terminal']}\s*$/iu");

                    if ($terminalDep) {
                        $s->departure()
                            ->terminal(trim(str_replace('Terminal', '', $terminalDep)));
                    }

                    // Arrival
                    $name = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root2);
                    $dateArr = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][position()=3 or position()=4][{$ruleTime}]", $root2)));
                    $s->arrival()
                        ->code($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root2))
                    ;

                    if (preg_match("/^\s*(?:.+,)?\s*{$patterns['terminal']}\s*$/iu", $dateArr)) {
                        $s->arrival()->terminal($dateArr);
                        $dateArr = '';
                    }

                    if (empty($dateArr) && ($date = strtotime($this->normalizeDate($name)))) {
                        $dateArr = $date;
                    } else {
                        $s->arrival()
                            ->name($name);
                    }
                    $s->arrival()
                        ->date($dateArr);
                    $terminalArr = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][last()]", $root2, true, "/^\s*(?:.+,)?\s*{$patterns['terminal']}\s*$/iu");

                    if ($terminalArr) {
                        $s->arrival()->terminal(trim(str_replace('Terminal', '', $terminalArr)));
                    }

                    // Extra
                    $s->extra()
                        ->duration($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root2))
                        ->cabin($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root2), true, true)
                    ;

                    $seats = array_filter($this->http->FindNodes("./following::text()[normalize-space()='{$pnr}'][1]/ancestor::td[count(./table)=2][1]/ancestor::table[1]//td[2][not(.//td)][not(contains(.,'{$pnr}')) and not(contains(.,','))]",
                        $root, "/^\d+[A-z]$/"));

                    if (!empty($seats)) {
                        $s->extra()->seats($seats);
                    }
                }
            }
        }

        return $email;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            // MON, 29 MAY '17
            '#^\s*\w+,\s+(\d+)\s+(\w+)\s+\'(\d{2})\s*$#',
            // 11:00 hrs, 29 May
            '#^\s*(\d+:\d+)\s+[hrs]*,\s+(\d+)\s+(\w+)\s*$#',
            // Sat Oct 21 16:40:00 IST 2017
            '/^\w+\s+(\w+)\s+(\d+)\s+(\d+:\d+:\d+)\s+\w+\s+(\d+)$/',
            // Sat, 06 Sep 14, 23:10 hrs
            '/^\w+,\s+(\d+ \w+ \d{2,4},\s+\d+:\d+)\s+\w+$/',
        ];
        $out = [
            '$1 $2 20$3',
            '$2 $3 ' . $year . ' $1',
            '$2 $1 $4, $3',
            '$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
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

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        } elseif (preg_match('/([\d\.]+)/', $node, $m)) {
            return ['Total' => $m[1]];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function starts($field, string $node = 'normalize-space()'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(' . $node . ', "' . $s . '")';
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
