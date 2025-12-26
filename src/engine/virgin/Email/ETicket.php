<?php

namespace AwardWallet\Engine\virgin\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "virgin/it-35660038.eml, virgin/it-35717342.eml, virgin/it-9116649.eml, virgin/it-9787273.eml, virgin/it-9830481.eml";

    public static $dictionary = [
        'en' => [
            'confNumber'   => ['Your booking reference is'],
            'passengers'   => ['Passengers'],
            'Total Amount' => ['Total', 'Total Amount'],
            'spendAwards'  => ['Total Paid Miles:', 'Total Paid Virgin Points:'],
        ],
    ];

    public $lang = '';
    private $date;

    public function parseHtml(Email $email): void
    {
        $f = $email->add()->flight();

        // Passengers
        $travellers = $this->http->FindNodes('//tr[not(.//tr) and' . $this->starts("eTicket number") . ']/preceding-sibling::tr[normalize-space(.)][1]', null, "#(.+?)\s*(?:\(.+|$)#");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes('//text()[' . $this->eq($this->t('passengers')) . ']/ancestor::tr[1]/following-sibling::tr[not(.//tr)]/descendant::text()[string-length(normalize-space(.))>4][1][ ./ancestor::*[self::b or self::strong] ]', null, "#(.+?)\s*(?:\(.+|$)#");
        }

        $f->general()
            ->confirmation($this->nextText($this->t('confNumber')))
            ->travellers($travellers, true);

        // TicketNumbers
        $ticketNumbers = $this->http->FindNodes('//td[not(.//td) and ' . $this->starts("eTicket number") . ']', null, '/eTicket number\s*([\d\-]{7,})\s*$/');
        $ticketNumberValues = array_values(array_filter($ticketNumbers));

        if (!empty($ticketNumberValues[0])) {
            $f->setTicketNumbers(array_unique($ticketNumberValues), false);
        }

        // AccountNumbers
        $accountNumbers = $this->http->FindNodes('//td[not(.//td) and ' . $this->starts("Flying Club number") . ']', null, '/Flying Club number\s*(\*+[\d]{4})\s*$/');
        $accountNumbersValues = array_values(array_filter($accountNumbers));

        if (!empty($ticketNumberValues[0])) {
            $f->setAccountNumbers(array_unique($accountNumbersValues), true);
        }

        // Currency
        $currencyCode = null;
        $currencyVal = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Amount'))}] ][1]/*[normalize-space()][2]");
        $this->logger->error($currencyVal);

        if ($currencyVal !== null) {
            $currency = $this->currency($currencyVal);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currency);
        }

        // SpentAwards
        $spentAwards = implode(' ', $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('spendAwards'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]"));

        if (preg_match('/^.*\d.*$/', $spentAwards) > 0) {
            // 47500 Virgin Points
            $f->price()
                ->spentAwards($spentAwards);
        }

        // TotalCharge
        $totals = array_filter($this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Amount'))}] ]/*[normalize-space()][2]", null, '/^[^\-\d)(]+[ ]*(\d[,.‘\'\d ]*)$/u'));

        $totalSum = 0;

        foreach ($totals as $total) {
            $totalSum += PriceHelper::parse($total, $currencyCode);
        }

        if (!empty($totalSum)) {
            $f->price()
                ->total($totalSum);
        }

        // BaseFare
        $fares = array_filter($this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Base Fare'))}] ]/*[normalize-space()][2]", null, '/^[^\-\d)(]+[ ]*(\d[,.‘\'\d ]*)$/u'));

        $baseFare = 0;

        foreach ($fares as $fare) {
            $baseFare += PriceHelper::parse($fare, $currencyCode);
        }

        if (!empty($baseFare)) {
            $f->price()
                ->cost($baseFare);
        }

        if (empty($baseFare) && !empty($spentAwards)) {
            unset($baseFare);
        }

        $pXpath = "//text()[{$this->eq('Base Fare')}]/ancestor::tr[1]/following-sibling::tr[ descendant::text()[normalize-space()][1][{$this->eq('Total Amount')}] ]/ancestor::*[1]/tr[count(*[normalize-space()])=2]";
        $fees = $this->http->XPath->query($pXpath);
        $isFee = false;

        foreach ($fees as $root) {
            $name = $this->http->FindSingleNode("*[normalize-space()][1]", $root);
            $fee = PriceHelper::parse($this->http->FindSingleNode("*[normalize-space()][2]", $root, null, '/^[^\-\d)(]+[ ]*(\d[,.‘\'\d ]*)$/u'), $currencyCode);

            if (preg_match("/^{$this->opt('Total Amount')}$/i", $name) > 0) {
                $isFee = false;
            }

            if ($isFee === true && !empty($name) && $fee !== null) {
                $f->price()
                    ->fee($name, $fee);
            }

            if (preg_match("/^{$this->opt('Base Fare')}$/i", $name) > 0) {
                $isFee = true;
            }
        }

        // TripSegments
        $xpath = "//img[contains(@src,'/arrow_grey.png')]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length == 0) {
            $xpath = "//text()[normalize-space()='Departing']/preceding::img[1]/ancestor::tr[1]";
            $segments = $this->http->XPath->query($xpath);
        }

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            $ddate = $adate = $dtime = $atime = null;

            $patternDate = "/^(?<date>.{3,}?)\s+{$this->opt($this->t('at'))}\s+(?<time>\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)$/u";

            $dateDepTexts = $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/*[1]/descendant::text()[normalize-space()][position()>1]", $root);
            $dateDepVal = implode(' ', $dateDepTexts);

            if (preg_match($patternDate, $dateDepVal, $m)) {
                $ddate = $this->normalizeDate($m['date']);
                $dtime = $m['time'];
            }

            $s->departure()
                ->name($this->http->FindSingleNode("./td[1]", $root, true, "#(.*?)\s+\(\s*[A-Z]{3}\s*\)#"))
                ->code($this->http->FindSingleNode("./td[1]", $root, true, "#\(\s*([A-Z]{3})\s*\)#"))
                ->date(strtotime($dtime, $ddate));

            // DepartureTerminal
            $terminalDep = $this->http->FindSingleNode('./following-sibling::tr[2]/td[1]', $root);

            if ($terminalDep) {
                $s->departure()
                    ->terminal(preg_replace('/.*\s*Terminal\s*([A-Z\d]+)$/i', '$1', $terminalDep));
            }

            // DepDate

            // ArrCode
            $s->arrival()
                ->code($this->http->FindSingleNode("./td[3]", $root, true, "#\(\s*([A-Z]{3})\s*\)#"))
                ->name($this->http->FindSingleNode("./td[3]", $root, true, "#(.*?)\s+\(\s*[A-Z]{3}\s*\)#"));

            // ArrivalTerminal
            $terminalArr = $this->http->FindSingleNode('./following-sibling::tr[2]/td[3]', $root);

            if ($terminalArr) {
                $s->arrival()
                    ->terminal(preg_replace('/.*\s*Terminal\s*([A-Z\d]+)$/i', '$1', $terminalArr));
            }

            // ArrDate
            $dateArrTexts = $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/*[3]/descendant::text()[normalize-space()][position()>1]", $root);

            if (count($dateArrTexts) === 3 && !empty($s->getDepDate()) && !empty($dateDepTexts[1])) {
                // Sat      at 19:55
                array_splice($dateArrTexts, 1, 0, $dateDepTexts[1]);
            }

            $dateArrVal = implode(' ', $dateArrTexts);

            if (preg_match($patternDate, $dateArrVal, $m)) {
                $adate = $this->normalizeDate($m['date']);
                $atime = $m['time'];
            }

            $s->arrival()->date(strtotime($atime, $adate));

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./following-sibling::tr[3]/td[1]', $root);

            if (preg_match('/^Flight Number\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                if (empty($matches['airline'])) {
                    $s->airline()
                        ->noName();
                } else {
                    $s->airline()
                        ->name($matches['airline']);
                }

                $s->airline()
                    ->number($matches['flightNumber']);
            }

            // Cabin
            // BookingClass
            $classText = $this->http->FindSingleNode('./following-sibling::tr[4]/descendant::text()[normalize-space(.)][1]', $root);

            if (preg_match('/^(?<cabin>\w[\w\s]+\w)\s+\(\s*(?<class>[A-Z]{1,2})\s*\)$/', $classText, $matches)) { // Economy ( O )
                $s->extra()
                    ->cabin($matches['cabin'])
                    ->bookingCode($matches['class']);
            }

            // Operator
            $operator = $this->http->FindSingleNode("./following-sibling::tr[position()<7][" . $this->contains("Operated by") . "]/td[1]", $root, true, "#Operated by\s*(.*?\(\s*\w{2}\s*\))#");

            if ($operator) {
                $s->airline()
                    ->operator($operator);
                // AirlineName
                if (empty($s->getAirlineName()) || $s->getNoAirlieName() == true) {
                    if (preg_match('/\(\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\)\s*$/', $operator, $matches)) { // Operated by DELTA AIR LINES INC. ( DL )
                        $s->airline()
                            ->name($matches['airline']);
                    }
                }
            }

            if (!isset($dopInfo) && !empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $nodes = $this->http->XPath->query("//td[not(.//td) and " . $this->starts([$s->getDepCode() . " - " . $s->getArrCode() . ' :', $s->getDepCode() . " - " . $s->getArrCode() . ':']) . "]");

                if (!isset($dopInfo) && $nodes->length > 0) {
                    $dopInfo = [];
                }

                foreach ($nodes as $roots) {
                    $fls = array_filter(str_replace(' ', '', explode(":", $roots->nodeValue)));
                    $info1 = $this->http->FindNodes("./following-sibling::td[1]//text()[normalize-space()]", $roots);

                    if (count($fls) == count($info1)) {
                        foreach ($info1 as $i => $value) {
                            if (preg_match("#Seat\s*(\d{1,3}[A-Z])\b#", $value, $m)) {
                                $dopInfo[$fls[$i]]['Seats'][] = $m[1];
                            }
                        }
                    }

                    $info2 = $this->http->FindNodes("./following-sibling::td[2]//text()[normalize-space()]", $roots);

                    if (count($fls) == count($info2)) {
                        foreach ($info2 as $i => $value) {
                            if (preg_match("#^\s*\(\s*(\S.*?)\s*\)\s*$#", $value, $m)) {
                                $dopInfo[$fls[$i]]['Cabin'][] = $m[1];
                            }
                        }
                    }
                }
            }

            foreach ($f->getSegments() as $segment) {
                if (isset($dopInfo[$segment->getDepCode() . '-' . $segment->getArrCode()]['Seats']) && count($dopInfo[$segment->getDepCode() . '-' . $segment->getArrCode()]['Seats']) > 0) {
                    $segment->setSeats($dopInfo[$segment->getDepCode() . '-' . $segment->getArrCode()]['Seats']);
                }

                if (isset($dopInfo[$segment->getDepCode() . '-' . $segment->getArrCode()]['Cabin']) && count($dopInfo[$segment->getDepCode() . '-' . $segment->getArrCode()]['Cabin']) > 0) {
                    $segment->setCabin(implode(", ", array_unique($dopInfo[$segment->getDepCode() . '-' . $segment->getArrCode()]['Cabin'])));
                }
            }

            // Seats
            /*if (!empty($itsegment['DepCode']) && !empty($itsegment['ArrCode']) && !empty($dopInfo[$itsegment['DepCode'] . '-' . $itsegment['ArrCode']])
                    && !empty($dopInfo[$itsegment['DepCode'] . '-' . $itsegment['ArrCode']]['Seats'])) {
                $itsegment['Seats'] = $dopInfo[$itsegment['DepCode'] . '-' . $itsegment['ArrCode']]['Seats'];
            }*/

            // Cabin
            /*if (empty($itsegment['Cabin']) && !empty($itsegment['DepCode']) && !empty($itsegment['ArrCode']) && !empty($dopInfo[$itsegment['DepCode'] . '-' . $itsegment['ArrCode']])
                    && !empty($dopInfo[$itsegment['DepCode'] . '-' . $itsegment['ArrCode']]['Cabin'])) {
                $cabin = array_unique(array_filter($dopInfo[$itsegment['DepCode'] . '-' . $itsegment['ArrCode']]['Cabin']));

                if (count($cabin) == 1) {
                    $itsegment['Cabin'] = array_shift($cabin);
                }
            }*/
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@virginatlantic.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Virgin Atlantic Airways e-Ticket') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".virginatlantic.com/") or contains(@href,"www.virginatlantic.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = true;

        $this->assignLang();

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['passengers'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['passengers'])}]")->length > 0
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

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            "/^([-[:alpha:]]+)[,.\s]+(\d{1,2})\s*([[:alpha:]]+)$/u", // Wed 29NOV
        ];
        $out = [
            "$2 $3 {$year}",
        ];
        $outWeek = [
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum, 6);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s): ?string
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
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
}
