<?php

namespace AwardWallet\Engine\regalwings\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightsConfirmed extends \TAccountChecker
{
    public $mailFiles = "regalwings/it-777333469.eml";

    public $detectFrom = "@regalwings.com";
    public $detectSubject = [
        // en
        ' - Flights Confirmed',
    ];

    public $year; // год указанный в письме
    public $dateRelative;
    public $emailSubject;
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Flights'    => 'Flights',
            'Total Cost' => 'Total Cost',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]regalwings\.com$/", $from) > 0;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['regalwings.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['choosing Regal Travel Group'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->year = $this->http->FindSingleNode("//text()[{$this->starts($this->t('COPYRIGHT'))}]",
            null, true, "/{$this->opt($this->t('COPYRIGHT'))}\s*(\d{4})\s*$/");

        $this->dateRelative = EmailDateHelper::getEmailDate($this, $parser);

        if (!empty($this->dateRelative)) {
            $this->dateRelative = strtotime('-1 week', strtotime($parser->getDate()));
        } else {
            $date = strtotime('-6 month', strtotime($parser->getDate()));

            if (!empty($this->year) && date('Y', $date) == $this->year) {
                $this->dateRelative = $date;
            }
        }

        $this->emailSubject = $parser->getSubject();

        $this->parseEmailHtml($email);

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

    public function niceTravellers($name)
    {
        return preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1', $name);
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Flights"]) && !empty($dict["Total Cost"])) {
                if ($this->http->XPath->query("//*[{$this->eq($dict['Flights'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->eq($dict['Total Cost'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // https://regalwings.com/quote/invoice/V1RS4cK6xAdQqa09wdGNbOZfkq4RVgvO-22919
        $link = $this->http->FindSingleNode("//a/@href[contains(., 'regalwings.com') and contains(., 'quote') and contains(., 'invoice')]");

        if (!empty($link)) {
            $this->parseByUrl($email, $link);
        }

        if (count($email->getItineraries()) === 0) {
            $this->parseHtml($email);
        }

        return true;
    }

    private function parseHtml(Email $email)
    {
        // Travel Agency
        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('issued your tickets for'))}]",
            null, true, "/{$this->opt($this->t('issued your tickets for'))}\s*([A-Z\d]{5,})\s*\./");

        if (empty($conf) && preg_match("/\b([A-Z\d]{5,}) - Flights Confirmed/", $this->emailSubject, $m)) {
            $conf = $m[1];
        }
        $email->ota()
            ->confirmation($conf);

        // Flights
        $f = $email->add()->flight();

        // General
        $confs = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Your confirmation code is'))}]/following::text()[normalize-space()][1]",
            null, "/^\s*([A-Z\d]{5,7})\s*$/")));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $f->general()
            ->travellers($this->niceTravellers($this->http->FindNodes("//text()[{$this->eq($this->t('Total Cost'))}]/following::text()[normalize-space()][1]/ancestor::tr[following-sibling::tr[{$this->starts($this->t('Total'))}]]/ancestor::*[1]/*/*[1][contains(., '/')]")), true);

        // Segments
        $nodes = $this->http->XPath->query("//tr[count(*) = 3][*[2][not(normalize-space())]//*[contains(translate(@style, ' ', ''), 'background:url')]]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("preceding::tr[1][count(*) = 2]/*[1]", $root))
                ->noNumber();
            /*
              Dec 10th
              10:40pm
              JFK - New York City, US
            */
            $re = "/^\s*(?<date>.+)\s+(?<time>\d+:\d+.+)\n\s*(?<code>[A-Z]{3})\s*-\s*(?<name>.+)\s*$/u";
            // Departure
            $departure = implode("\n", $this->http->FindNodes("*[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match($re, $departure, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($this->parseDate($m['date'], $m['time']))
                ;
            }

            // Arrival
            $arrival = implode("\n", $this->http->FindNodes("*[normalize-space()][2]//text()[normalize-space()]", $root));

            if (preg_match($re, $arrival, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($this->parseDate($m['date'], $m['time']))
                ;
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode("preceding::tr[1][count(*) = 2]/*[2]", $root));
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Cost'))}]/following::td[{$this->eq($this->t('Total'))}]/following-sibling::*[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $email->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        }

        return true;
    }

    private function parseByUrl(Email $email, $url)
    {
        $http2 = clone $this->http;
        $http2->GetURL($url);
        $this->http->SetEmailBody($http2->Response['body']);

        if ($http2->XPath->query("descendant::text()[normalize-space()][not(ancestor::style)][position() < 7][{$this->eq($this->t('E-ticket AND INVOICE'))}]/following::text()[{$this->starts($this->t('Your flight from'))}]")->length == 0) {
            return false;
        }

        // Travel Agency
        $email->ota()
            ->confirmation($http2->FindSingleNode("//text()[{$this->eq($this->t('E-ticket AND INVOICE'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*-\s*([A-Z\d]{5,})\s*$/"));

        // Flights
        $f = $email->add()->flight();

        // General
        $airlineConfs = [];
        $confTexts = explode(',', implode(',', array_unique($http2->FindNodes("//text()[{$this->eq($this->t('Flight Confirmation:'))}]/ancestor::*[{$this->starts($this->t('Flight Confirmation:'))}][last()]",
            null, "/{$this->opt($this->t('Flight Confirmation:'))}\s*(.+)/"))));

        foreach ($confTexts as $cText) {
            if (preg_match("/^\s*([A-Z\d]{2})\s*\W\s*([A-Z\d]{5,7})\s*$/", $cText, $m)) {
                $airlineConfs[$m[2]][] = $m[1];
            }
        }

        foreach ($airlineConfs as $conf => $names) {
            $f->general()
                ->confirmation($conf, implode(', ', array_unique($names)));
        }

        $travellerNodes = $http2->XPath->query("//tr[{$this->eq($this->t('PASSENGERS'))}]/following-sibling::tr/td[normalize-space()]");

        foreach ($travellerNodes as $tRoot) {
            $name = $this->niceTravellers($http2->FindSingleNode("descendant::text()[normalize-space()][1]", $tRoot));
            $f->general()
                ->traveller($name, true);
            $tickets = array_filter($http2->FindNodes("descendant::text()[{$this->eq($this->t('Ticket #:'))}]/ancestor::*[{$this->starts($this->t('Ticket #:'))}][last()]",
                $tRoot, "/{$this->opt($this->t('Ticket #:'))}\s*(\d+.*)/"));

            foreach ($tickets as $ticket) {
                $f->issued()
                    ->ticket($ticket, false, $name);
            }
        }

        // Segments
        $nodes = $http2->XPath->query("//tr[count(*[normalize-space()]) = 4][*[normalize-space()][3]//img]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $http2->FindSingleNode("*[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,4})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            /*
              Sun, Dec 29
              +2 Days
              9:55PM
              JFK - New York City, NY (Terminal 5)
            */
            $re = "/^\s*(?<date>.+)(?:\n.+)?\s+(?<time>\d+:\d+.+)\n\s*(?<code>[A-Z]{3})\s*-\s*(?<name>.+?)\s*(?:\((?<terminal>.+)\))?$/u";
            // Departure
            $departure = implode("\n", $http2->FindNodes("*[normalize-space()][2]//text()[normalize-space()]", $root));

            if (preg_match($re, $departure, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->terminal(trim(preg_replace("/\s*terminal\s*/i", '', $m['terminal'] ?? '')), true, true)
                    ->date($this->parseDate($m['date'], $m['time']))
                ;
            }

            // Arrival
            $arrival = implode("\n", $http2->FindNodes("*[normalize-space()][4]//text()[normalize-space()]", $root));

            if (preg_match($re, $arrival, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->terminal(trim(preg_replace("/\s*terminal\s*/i", '', $m['terminal'] ?? '')), true, true)
                    ->date($this->parseDate($m['date'], $m['time']))
                ;
            }

            // Extra
            $extras = $http2->FindNodes("*[normalize-space()][3]//text()[normalize-space()]", $root);

            if (count($extras) == 2 && preg_match("/^(\s*\d+\s*(?:h|m))+\s*$/", $extras[0])) {
                $s->extra()
                    ->duration($extras[0])
                    ->aircraft($extras[1]);
            }

            $passTableXpath = "following::text()[normalize-space()][1][{$this->eq($this->t('Passenger'))}]/ancestor::tr[1]/following-sibling::tr";
            $s->extra()
                ->cabin(implode(', ', array_unique($http2->FindNodes($passTableXpath . '/*[3]', $root))));
            $meals = array_unique(array_filter(preg_replace('/^\s*No Preference\s*$/', '', $http2->FindNodes($passTableXpath . '/*[4]', $root))));

            if (!empty($meals)) {
                $s->extra()
                    ->meals($meals);
            }
            $passNodes = $http2->XPath->query($passTableXpath, $root);

            foreach ($passNodes as $pRoot) {
                $name = $this->niceTravellers($http2->FindSingleNode("*[1]", $pRoot));
                $seat = $http2->FindSingleNode("*[2]", $pRoot, true, "/^\s*\d{1,3}[A-Z]\s*$/");

                if (!empty($seat)) {
                    $s->extra()
                        ->seat($seat, true, true, $name);
                }
            }
        }

        // Price
        $total = $http2->FindSingleNode("//td[{$this->eq($this->t('Total Due'))}]/following-sibling::*[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $email->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        }

        $this->logger->debug('$http2 = ' . print_r($http2->Response, true));

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
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

    private function parseDate(?string $dateText, ?string $timeText)
    {
        // $this->logger->debug('$dateText = ' . print_r($dateText, true));
        // $this->logger->debug('$timeText = ' . print_r($timeText, true));

        if (empty($dateText) || empty($timeText)) {
            return null;
        }
        $date = $this->normalizeDate($dateText);

        // $this->logger->debug('$date = ' . print_r($date, true));
        // $this->logger->debug('$timeText = '.print_r( $timeText,true));

        if (!empty($date) && !empty($timeText)) {
            return strtotime($timeText, $date);
        }

        return null;
    }

    private function normalizeDate(?string $date)
    {
        // $this->logger->debug('$date IN = ' . print_r($date, true));
        $year = $this->year ?? date('Y', $this->dateRelative);

        $in = [
            // Thu, Dec 12
            '/^\s*([[:alpha:]]+),\s*([[:alpha:]]+)\s+(\d{1,2})\s*$/iu',
            // Dec 12th
            '/^\s*([[:alpha:]]+)\s+(\d{1,2})(?:st|nd|rd|th)\s*$/ui',
        ];

        // $year - for date without year and with week
        // %year% - for date without year and without week

        $out = [
            '$1, $3 $2 ' . $year,
            // '$2 $1 $3',
            '$2 $1 %year%',
        ];

        $date = preg_replace($in, $out, trim($date));
        // $this->logger->debug('$date RE = ' . print_r($date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (!empty($this->dateRelative) && $this->dateRelative > strtotime('01.01.2000') && strpos($date, '%year%') !== false && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{1,2}.*))?$/', $date, $m)) {
            // $this->logger->debug('$date (no week, no year) = ' . print_r($m['date'], true));
            $date = EmailDateHelper::parseDateRelative($m['date'], $this->dateRelative);

            if (!empty($date) && !empty($m['time'])) {
                return strtotime($m['time'], $date);
            }

            return $date;
        } elseif (($year) > 2000 && preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $date, $m)) {
            // $this->logger->debug('$date (week no year) = ' . print_r($date, true));
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $date)) {
            // $this->logger->debug('$date (year) = ' . print_r($date, true));

            return strtotime($date);
        } else {
            return null;
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function currency($s)
    {
        $s = trim($s);

        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
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

        return $s;
    }
}
