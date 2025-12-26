<?php

namespace AwardWallet\Engine\turkish\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "turkish/it-623158165-tr.eml, turkish/it-634227144.eml, turkish/it-733907310.eml";

    public $lang = '';

    public static $dictionary = [
        'tr' => [
            'dear'                    => 'Sayın',
            'Yolcularımız'            => 'Yolcularımız',
            'confNumber'              => ['Rezervasyon kodu'],
            'Previous Flight Time'    => 'Önceki Uçuş Saati',
            'PREVIOUS DEPARTURE TIME' => ['ESKİ KALKIŞ SAATİ'],
            'PREVIOUS ARRIVAL TIME'   => ['ESKİ VARIŞ SAATİ'],
            'CANCELED FLIGHT'         => 'İPTAL EDİLEN UÇUŞ',
            'flightCode'              => ['UÇUŞ KODU'],
            'NEW FLIGHT DATE'         => ['YENİ UÇUŞ TARİHİ', 'UÇUŞ TARİHİ'],
            'cabin'                   => 'KABİN',
            'timeDep'                 => ['YENİ KALKIŞ SAATİ', 'ESKİ KALKIŞ SAATİ'],
            'timeArr'                 => ['YENİ VARIŞ SAATİ', 'ESKİ VARIŞ SAATİ'],
        ],
        'en' => [
            'dear'          => 'Dear',
            'Yolcularımız'  => 'Passengers',
            'confNumber'    => ['Reservation code', 'Reservation Code'],
            // 'Previous Flight Time' => '',
            // 'PREVIOUS DEPARTURE TIME' => '',
            // 'PREVIOUS ARRIVAL TIME' => '',
            // 'CANCELED FLIGHT' => ''
            'flightCode'      => ['FLIGHT CODE'],
            'NEW FLIGHT DATE' => ['NEW FLIGHT DATE', 'FLIGHT DATE'],
            'cabin'           => 'CABIN',
            'timeDep'         => ['NEW DEPARTURE TIME', 'PREVIOUS DEPARTURE TIME'],
            'timeArr'         => ['NEW ARRIVAL TIME', 'PREVIOUS ARRIVAL TIME'],
        ],
    ];

    private $subjects = [
        'tr' => ['Yolları Sefer Tehir Bilgisi'],
        'en' => ['Flight Delay Information'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@thy\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true
            && strpos($headers['subject'], 'Turkish Airlines') === false
            && strpos($headers['subject'], 'Türk Hava') === false
        ) {
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
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,"//air.tk/") or contains(@href,"com.turkishairlines.mobile")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('FlightChange' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('dear'))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");

        if (preg_match("/^\s*{$this->opt($this->t('Yolcularımız'))}\s*$/iu", $traveller)) {
        } else {
            $f->general()->traveller($traveller);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpathAirportCode = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"';
        $xpathRoute = "count(*)=3 and *[1][{$xpathAirportCode}] and *[2][normalize-space()='' and descendant::img] and *[3][{$xpathAirportCode}]";
        $xpathFilter = "not({$this->starts($this->t('Previous Flight Time'))} or descendant::*[{$this->eq($this->t('PREVIOUS DEPARTURE TIME'))}] or descendant::*[{$this->eq($this->t('PREVIOUS ARRIVAL TIME'))}])";
        $segments = $this->http->XPath->query("//tr[{$xpathRoute}]/ancestor::*[ descendant::*[{$this->eq($this->t('flightCode'))}] ][1][{$xpathFilter}]");

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('CANCELED FLIGHT'))}]")->length > 0) {
            $f->general()
                ->cancelled();
            $segments = $this->http->XPath->query("//tr[{$xpathRoute}]/ancestor::*[ descendant::*[{$this->eq($this->t('flightCode'))}] ][1]");
        }
        $this->logger->debug("//tr[{$xpathRoute}]/ancestor::*[ descendant::*[{$this->eq($this->t('flightCode'))}] ][1][{$xpathFilter}]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('NEW FLIGHT DATE'))}]/following-sibling::tr[normalize-space()][1]", $root)));
            $flight = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('flightCode'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $cabin = $this->http->FindSingleNode("descendant::tr[ *[2][{$this->eq($this->t('cabin'))}] ]/following-sibling::tr[normalize-space()][1]/*[2]", $root);

            if (preg_match("/^[(\s]*([A-Z]{1,2})[\s)]*$/", $cabin, $m)) {
                // K    |    (K)
                $s->extra()->bookingCode($m[1]);
            } elseif (preg_match("/^(.{2,}?)[(\s]+([A-Z]{1,2})[\s)]*$/", $cabin, $m)) {
                // Business (K)
                $s->extra()->cabin($m[1])->bookingCode($m[2]);
            } elseif (!empty($cabin)) {
                $s->extra()->cabin($cabin);
            }

            $codeDep = $this->http->FindSingleNode("descendant::tr[{$xpathRoute}]/*[1]", $root);
            $codeArr = $this->http->FindSingleNode("descendant::tr[{$xpathRoute}]/*[3]", $root);

            $s->departure()->code($codeDep);
            $s->arrival()->code($codeArr);

            $xpathAirportNames = "descendant::tr[{$xpathRoute}]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]/descendant-or-self::*[count(*)=3 and count(*[normalize-space()])>1][1]";

            $nameDep = implode(', ', $this->http->FindNodes($xpathAirportNames . "/*[1]/descendant::text()[normalize-space()]", $root));
            $nameArr = implode(', ', $this->http->FindNodes($xpathAirportNames . "/*[3]/descendant::text()[normalize-space()]", $root));

            if ($nameDep !== $nameArr) {
                $s->departure()->name($nameDep);
                $s->arrival()->name($nameArr);
            }

            $duration = $this->http->FindSingleNode($xpathAirportNames . "/*[2]", $root, true, '/^\d.*/');
            $s->extra()->duration($duration, false, true);

            $timeDep = $this->http->FindSingleNode("descendant::*[ *[normalize-space()][1][{$this->eq($this->t('timeDep'))}] ]/*[normalize-space()][2]", $root, true, "/^{$patterns['time']}/");
            $timeArr = $this->http->FindSingleNode("descendant::*[ *[normalize-space()][1][{$this->eq($this->t('timeArr'))}] ]/*[normalize-space()][2]", $root, true, "/^{$patterns['time']}/");

            if ($date) {
                if ($timeDep) {
                    $s->departure()->date(strtotime($timeDep, $date));
                }

                if ($timeArr) {
                    $s->arrival()->date(strtotime($timeArr, $date));
                }
            }
        }

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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['flightCode'])) {
                continue;
            }

            $this->logger->debug("//*[{$this->contains($phrases['confNumber'])}]");
            $this->logger->debug("//*[{$this->contains($phrases['flightCode'])}]");

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['flightCode'])}]")->length > 0
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\b/u', $text, $m)) {
            // 17 Aralık 2023 Pazar
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b([[:alpha:]]+)\s+(\d{1,2})[,\s]+(\d{4})$/u', $text, $m)) {
            // Wednesday, January 17, 2024
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
