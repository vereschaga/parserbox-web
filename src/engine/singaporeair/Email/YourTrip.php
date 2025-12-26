<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourTrip extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-206898729.eml, singaporeair/it-638223942.eml, singaporeair/it-727319841.eml, singaporeair/it-733893162.eml, singaporeair/it-735169557.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Dear '      => ['Dear ', 'Hi '],
            'confNumber' => ['Booking reference', 'BOOKING REFERENCE'],
            'flight'     => ['Flight', 'FLIGHT', 'Depart', 'Return'],
            'welcome'    => ['we can’t wait to welcome you on board'],
        ],
    ];

    public $year = '';

    private $subjects = [
        'en' => ['Your upcoming trip'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]singaporeair\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".singaporeair.com/") or contains(@href,"email.singaporeair.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing to fly with Singapore Airlines") or contains(normalize-space(),"Singapore Airlines. All Rights Reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $xpathNoDisplay = 'not(ancestor-or-self::*[contains(translate(@style," ",""),"display:none")])';
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        $this->year = $this->http->FindSingleNode("//text()[{$this->contains($this->t('All Rights Reserved'))} and {$xpathNoDisplay}]", null, true, "/\b(20\d{2})\b/");

        if (empty($this->year)) {
            $this->year = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Singapore Airlines')][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd')]", null, true, "/\s(\d+)\s*{$this->opt('Singapore Airlines')}/");
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $f = $email->add()->flight();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//tr[not(.//tr) and {$this->contains($this->t('welcome'))}]", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*,\s*{$this->opt($this->t('welcome'))}(?:\s*[,.;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true,
                "/{$this->opt($this->t('Dear '))}\s*(?:(?:Mrs|Mr|Ms|Dr|Miss|Prof)\s+)?\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/");
        }

        if (empty($traveller)) {
            $traveller = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1]/descendant::text()[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd.')]/ancestor::tr[1]", null, "/^\s*\d+\.(?:\s*(?:Mrs|Mr|Ms))?(.+)/u")));
        }

        if (in_array($traveller, ['Valued Customer'])) {
        } elseif (is_array($traveller) === true) {
            $f->general()->travellers(str_replace("Join KrisFlyer", "", $traveller));
        } else {
            $f->general()->traveller($traveller);
        }

        $accounts = [];

        if (is_array($traveller) === true) {
            foreach ($traveller as $pax) {
                $account = $this->http->FindSingleNode("//text()[{$this->contains($pax)}]/following::text()[normalize-space()][1]", null, true, "/^(\d{4,})$/");

                if (!empty($account)) {
                    $accounts[] = $account;
                }
            }
        }

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, false);
        }

        $confirmation = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('confNumber'))} and {$xpathNoDisplay}])[1]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[ {$this->eq($this->t('confNumber'))} and {$xpathNoDisplay} and following::text()[normalize-space()] ]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total to be paid']/following::text()[normalize-space()][1]");

        if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $segments = $this->http->XPath->query($xpath = "//tr[ *[1][{$xpathTime}] and *[2][descendant::img and normalize-space()=''] and *[3][{$xpathTime}] ][{$xpathNoDisplay}]");
        $this->logger->debug('Segments xPath: ' . $xpath);

        $type = '';

        if ($segments->length > 0) {
            $route = implode(' ', $this->http->FindNodes("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]", $segments->item(0)));

            if (preg_match("/^(.{3,}?)\s+{$this->opt($this->t('to'))}\s+(.{3,})$/", $route, $m)) {
                $type = '1';
                $this->parseSegment1($f, $segments);
            } else {
                $type = '3';
                $this->parseSegment3($f, $segments);
            }
        } elseif ($segments->length === 0
            && $this->http->XPath->query("//span[contains(@style, 'destination') or contains(@class, 'end')]/ancestor::tr[1]")->length > 0) {
            $segments = $this->http->XPath->query("//span[contains(@style, 'destination') or contains(@class, 'end')]/ancestor::tr[1]");
            $type = '2';
            $this->parseSegment2($f, $segments);
        }

        $email->setType('YourTrip' . $type . ucfirst($this->lang));

        return $email;
    }

    public function parseSegment1(\AwardWallet\Schema\Parser\Common\Flight $f, \DOMNodeList $segments)
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];
        $year = $this->year;

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = implode(' ', $this->http->FindNodes("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match('/\b(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $route = implode(' ', $this->http->FindNodes("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]", $root));

            if (preg_match("/^(.{3,}?)\s+{$this->opt($this->t('to'))}\s+(.{3,})$/", $route, $m)) {
                $s->departure()->name($m[1])->noCode();
                $s->arrival()->name($m[2])->noCode();
            }

            $dateDep = $dateArr = 0;
            $dateDepVal = $timeDep = $dateArrVal = $timeArr = null;

            // 20:35, 15 Feb (Tue)
            $patterns['timeDate'] = "/^(?<time>{$patterns['time']})[,\s]+(?<date>.*\d.*)$/";
            $patterns['date'] = '/^(?<date>\d{1,2}\s+[[:alpha:]]+)\s*\(\s*(?<wday>[-[:alpha:]]+)\s*\)$/u'; // 15 Feb (Tue)

            $departure = $this->http->FindSingleNode("*[1]", $root);

            if (preg_match($patterns['timeDate'], $departure, $m)) {
                $timeDep = $m['time'];
                $dateDepVal = $m['date'];
            }

            if ($dateDepVal && $timeDep && $year && preg_match($patterns['date'], $dateDepVal, $m)) {
                $weekDateNumber = WeekTranslate::number1($m['wday']);
                $dateDep = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $weekDateNumber);
            }

            $s->departure()->date(strtotime($timeDep, $dateDep));

            $arrival = $this->http->FindSingleNode("*[3]", $root);

            if (preg_match($patterns['timeDate'], $arrival, $m)) {
                $timeArr = $m['time'];
                $dateArrVal = $m['date'];
            }

            if ($dateArrVal && $timeArr && $year && preg_match($patterns['date'], $dateArrVal, $m)) {
                $weekDateNumber = WeekTranslate::number1($m['wday']);
                $dateArr = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $weekDateNumber);
            }

            $s->arrival()->date(strtotime($timeArr, $dateArr));
        }
    }

    public function parseSegment2(\AwardWallet\Schema\Parser\Common\Flight $f, \DOMNodeList $segments)
    {
        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flightInfo = $this->http->FindSingleNode("./preceding::tr[normalize-space()][2]", $root);

            if (preg_match("/\b(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,4})$/", $flightInfo, $m)
             || preg_match("/\b(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,4})[\s•]*(?<cabin>.+)$/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                if (isset($m['cabin']) && !empty($m['cabin'])) {
                    $s->extra()
                        ->cabin($m['cabin']);
                }
            }

            $flightDate = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^(?<depTime>[\d\:]+)\,\s*(?<depDay>\d+\s*\w+)\s*\((?<depWeek>\w+)\)\s*(?<arrTime>[\d\:]+)\,\s*(?<arrDay>\d+\s*\w+)\s*\((?<arrWeek>\w+)\)/", $flightDate, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['depWeek'] . ', ' . $m['depDay'] . ', ' . $m['depTime']));
                $s->arrival()
                    ->date($this->normalizeDate($m['arrWeek'] . ', ' . $m['arrDay'] . ', ' . $m['arrTime']));
            }

            $airportInfo = $this->http->FindSingleNode("./preceding::tr[1]", $root);

            if (preg_match("/^(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s+(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)$/", $airportInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode']);

                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode']);
            }
        }
    }

    public function parseSegment3(\AwardWallet\Schema\Parser\Common\Flight $f, \DOMNodeList $segments)
    {
        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flightInfo = $this->http->FindSingleNode("./preceding::tr[normalize-space()][2]", $root);

            if (preg_match("/\b(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,4})$/", $flightInfo, $m)
             || preg_match("/\b(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,4})[\s•]*(?<cabin>.+)$/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                if (isset($m['cabin']) && !empty($m['cabin'])) {
                    $s->extra()
                        ->cabin($m['cabin']);
                }
            }

            $dTime = $aTime = null;
            $info1 = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^\s*(?<depCode>[A-Z]{3})\s+(?<depTime>\d+:\d+)\s+(?<arrCode>[A-Z]{3})\s+(?<arrTime>\d+:\d+)\s*$/", $info1, $m)) {
                $s->departure()
                    ->code($m['depCode']);

                $s->arrival()
                    ->code($m['arrCode']);

                $dTime = $m['depTime'];
                $aTime = $m['arrTime'];
            }

            $info2 = $this->http->FindSingleNode("./preceding::tr[1]", $root);

            if (preg_match("/^\s*(?<depDay>\d+\s*\w+)\s*\((?<depWeek>\w+)\)\s+(?<arrDay>\d+\s*\w+)\s*\((?<arrWeek>\w+)\)/", $info2, $m)
                && !empty($dTime) && !empty($aTime)
            ) {
                $s->departure()
                    ->date($this->normalizeDate($m['depWeek'] . ', ' . $m['depDay'] . ', ' . $dTime));
                $s->arrival()
                    ->date($this->normalizeDate($m['arrWeek'] . ', ' . $m['arrDay'] . ', ' . $aTime));
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 3;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['flight'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['flight'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
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

    private function normalizeDate($str)
    {
        $year = $this->year;
        //$this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Fri, 02 Feb
            "#^\s*(\w+\,\s*\d+\s*\w+)\,\s*([\d\:]+)$#iu",
        ];
        $out = [
            "$1 $year, $2",
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
