<?php

namespace AwardWallet\Engine\copaair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HasChanged extends \TAccountChecker
{
    public $mailFiles = "copaair/it-187556565-es.eml, copaair/it-292756328.eml, copaair/it-643170306-fr.eml, copaair/it-645060176.eml, copaair/it-724585641-es.eml, copaair/it-720213614-es.eml, copaair/it-717843355-pt.eml";
    public $detectSubjects = [
        // en
        'Pending Check-In',
        'Your travel itinerary has changed',
        "It's time to check-in for your trip.",
        // pt
        'Check-in Pendente',
        'Seu itinerário de viagem foi alterado',
        // es
        'Check-In Pendiente',
        'Tu itinerario de viaje ha cambiado',
        'Es hora de realizar Check-In para tu viaje',
        'Ajuste menor a tu itinerario de viaje',
        'Hemos modificado tu itinerario de viaje. Tu acción es requerida',
    ];

    public $lang = '';

    public $emailDate = 0;
    public $year = '';

    public static $dictionary = [
        "fr" => [
            'confNumber'       => ['Code de la réservation'],
            'detectBody'       => ['Information des passagers'],
            // 'previousItinerary' => '',
            // 'Frequent flyer number' => [''],
            'Seat'                   => ['Siège(s)', 'Sièges', 'Siège'],
            // 'Operated by' => '',
        ],
        "pt" => [
            'confNumber'             => ['Código de Reserva', 'Código de reserva'],
            'detectBody'             => ['Seu itinerário atualizado', 'Detalhes do itinerário', 'Itinerário do voo'],
            // 'previousItinerary' => '',
            'Frequent flyer number'  => ['Número de viajero frecuente'],
            'Seat'                   => ['Asiento(s)', 'Asientos', 'Asiento'],
            'Operated by'            => 'Operado por',
        ],
        "es" => [
            'confNumber'            => ['Código de Reservación', 'Código de Reserva', 'Código de reserva', 'Código de la reserva'],
            'detectBody'            => ['Tu itinerario actualizado', 'Detalles del itinerario', 'Su itinerario actualizado'],
            'previousItinerary'     => 'Itinerario anterior',
            'Frequent flyer number' => ['Número de viajero frecuente'],
            'Seat'                  => ['Asiento(s)', 'Asientos', 'Asiento'],
            'Operated by'           => 'Operado por',
        ],
        "en" => [
            'confNumber'       => ['Reservation Code', 'Reservation code'],
            'detectBody'       => ['Your updated itinerary', "It's time to check in", 'Pending Check-In'],
            // 'previousItinerary' => '',
            // 'Frequent flyer number' => [''],
            'Seat'                   => ['Seat(s)', 'Seats', 'Seat'],
            // 'Operated by' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query("//title[contains(normalize-space(),'Copa Airlines')] | //img[contains(@src,'.copaair.com/')]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cns.copaair.com') !== false;
    }

    public function ParseFlight(Email $email): void
    {
        $xpathTime = 'contains(translate(.,"0123456789： ","∆∆∆∆∆∆∆∆∆∆:"),"∆:∆∆")';
        $xpathTime2 = '(starts-with(translate(normalize-space(),"0123456789： ","∆∆∆∆∆∆∆∆∆∆:"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789： ","∆∆∆∆∆∆∆∆∆∆:"),"∆∆:∆∆"))';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'status'        => '/^\b[-[:alpha:]]+(?:[ ]+[[:alpha:]]+){0,1}$/u', // Scheduled  |  Check-in available
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^\s*([A-Z\d]{5,7})\s*$/');
        $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
        $f->general()->confirmation($confirmation, $confirmationTitle);

        // segments: type-1
        $xpath = "//tr[ count(*)=3 and *[1][{$xpathTime}] and *[2][normalize-space()='' and descendant::img] and *[3][{$xpathTime}] ]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            // segments: type-2 (examples: it-720213614-es.eml, it-717843355-pt.eml)
            $xpath = "//tr[ count(*)=5 and *[1][{$xpathTime2}] and *[2][normalize-space()=''] and *[3][normalize-space()='' and descendant::img or normalize-space() and not(descendant::img)] and *[4][normalize-space()=''] and *[5][{$xpathTime2}] ][not(preceding::*[{$this->eq($this->t('previousItinerary'))}])]";
            $segments = $this->http->XPath->query($xpath);
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $dateVal = null;
            $td = $this->http->FindSingleNode("preceding::tr[not(.//tr[normalize-space()]) and normalize-space()][1]/*[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<date>.{4,}?)[•\s]+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])[- ]?(?<fn>\d+)\s*$/u", $td, $m)) {
                // Thu, 17Nov, 2022 • CM803    |    Thu, Nov 17 • CM803    |    17Nov • CM803
                $dateVal = $m['date'];

                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $date = null;

            if (preg_match("/^.{4,40}?\b\d{4}$/", $dateVal, $m)) {
                // Thu, 17Nov, 2022    |    17 Nov 2022
                $date = strtotime($this->normalizeDate($dateVal));
            } elseif (preg_match("/^(?<wday>[-[:alpha:]]+)[,\s]+(?<date>\d{1,2}\s*[[:alpha:]]+|[[:alpha:]]+\s*\d{1,2})(?:[,\s]+\d{3})?$/u", $dateVal, $m)) {
                // Thu, Nov 17    |    Thu, 17 Nov, 202
                $dateNormal = $this->normalizeDate($m['date']);
                $year = empty($this->year) ? date('Y', $this->emailDate) : $this->year;
                $weekDateNumber = WeekTranslate::number1($m['wday'], $this->lang) ?? WeekTranslate::number1($m['wday'], 'en');

                if ($dateNormal && $year && $weekDateNumber) {
                    $date = EmailDateHelper::parseDateUsingWeekDay($dateNormal . ' ' . $year, $weekDateNumber);
                }
            } elseif (preg_match("/^(?:\d{1,2}\s*[[:alpha:]]+|[[:alpha:]]+\s*\d{1,2})(?:[,\s]+\d{3})?$/u", $dateVal, $m)) {
                // 17Nov    |    17 Nov 202
                $dateNormal = $this->normalizeDate($dateVal);

                if (!empty($this->year)) {
                    $date = strtotime($dateNormal . ' ' . $this->year);
                } elseif (!empty($this->emailDate)) {
                    $date = EmailDateHelper::parseDateRelative($dateNormal, $this->emailDate, true);
                }
            }

            $duration = $this->http->FindSingleNode("*[3]", $root, true, "/^(?:\s*\d+\s*[hm])+$/i");
            $s->extra()->duration($duration, false, true);

            $dateDep = $dateArr = $nameDep = $nameArr = $codeDep = $codeArr = null;

            $depInfo = implode(' ', $this->http->FindNodes("*[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern = "/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*(?<time>{$patterns['time']})\s*$/", $depInfo, $m)) {
                // segments: type-1
                $dateDep = strtotime($m['time'], $date);
                $nameDep = $m['name'];
                $codeDep = $m['code'];
            } else {
                // segments: type-2
                $timeDep = $this->http->FindSingleNode("*[1]", $root, true, "/^{$patterns['time']}/");
                $dateDep = strtotime($timeDep, $date);
                $airportDep = $this->http->FindSingleNode("following::tr[not(.//tr[normalize-space()]) and normalize-space()][1]/*[normalize-space()][1]", $root);

                if (preg_match("/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*$/", $airportDep, $m)) {
                    $nameDep = $m['name'];
                    $codeDep = $m['code'];
                }
            }

            $arrInfo = implode(' ', $this->http->FindNodes("*[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $arrInfo, $m)) {
                $dateArr = strtotime($m['time'], $date);
                $nameArr = $m['name'];
                $codeArr = $m['code'];
            } else {
                $timeArr = $this->http->FindSingleNode("*[5]", $root, true, "/^{$patterns['time']}/");
                $dateArr = strtotime($timeArr, $date);
                $airportArr = $this->http->FindSingleNode("following::tr[not(.//tr[normalize-space()]) and normalize-space()][1]/*[normalize-space()][position()>1][last()]", $root);

                if (preg_match("/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*$/", $airportArr, $m)) {
                    $nameArr = $m['name'];
                    $codeArr = $m['code'];
                }
            }

            $s->departure()->date($dateDep)->name($nameDep)->code($codeDep);
            $s->arrival()->date($dateArr)->name($nameArr)->code($codeArr);

            $status = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $root, true, $patterns['status'])
                ?? $this->http->FindSingleNode("preceding::tr[not(.//tr[normalize-space()]) and normalize-space()][1]/*[normalize-space()][position()>1][last()]", $root, true, $patterns['status']);
            $s->extra()->status($status, false, true);

            $operator = $this->http->FindSingleNode("ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][position()<3][{$this->starts($this->t('Operated by'))}]", $root, true, "/^{$this->opt($this->t('Operated by'))}[:\s]+([^:]{2,30})$/");
            $s->airline()->operator($operator, false, true);
        }

        $accounts = [];
        $travellersRows = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->starts($this->t('Seat'))}] ]");

        foreach ($travellersRows as $tRow) {
            $passengerName = $this->normalizeTraveller($this->http->FindSingleNode("*[normalize-space()][1]/descendant::text()[normalize-space()][1]/ancestor::p[1]", $tRow, true, "/^{$patterns['travellerName']}$/u"));
            $f->general()->traveller($passengerName, true);

            $accountText = implode("\n", $this->http->FindNodes("*[normalize-space()][1]/descendant-or-self::*[ p[normalize-space()][2] ][1]/p[normalize-space()]", $tRow));

            if ((preg_match("/^(?<name>ConnectMiles Member|{$this->opt($this->t('Frequent flyer number'))})[: #]+(?<value>[-A-z\d]{3,30})$/m", $accountText, $m)
                || preg_match("/^(?<name>ConnectMiles\b[^\d\n]*?)[: ]*\n[# ]*(?<value>[-A-z\d]{3,30})$/m", $accountText, $m))
                && preg_match('/\d/', $m['value']) > 0 && !in_array($m['value'], $accounts)
            ) {
                $f->program()->account($m['value'], false, $passengerName, $m['name']);
                $accounts[] = $m['value'];
            }

            $seatsVal = $this->http->FindSingleNode("*[normalize-space()][2]/descendant-or-self::*[ node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Seat'))}] ]/node()[normalize-space() and not(self::comment())][2]", $tRow);
            $seatsVal = preg_replace(['/\b1\./', '/\s+/'], ' ', $seatsVal ?? '');
            $seatValues = preg_split('/(?:\s*[,;]\s*|\b \b)/', $seatsVal);

            foreach ($seatValues as $seatVal) {
                if (!preg_match('/^\d+[A-Z]$/', $seatVal)) {
                    $seatValues = [];

                    break;
                }
            }

            if (count($seatValues) > 0 && count($seatValues) === count($f->getSegments())) {
                foreach ($f->getSegments() as $i => $seg) {
                    $seg->extra()->seat($seatValues[$i], false, false, $passengerName);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!preg_match("/^(?:Re|fw|fwd)\b/i", $parser->getSubject())) {
            $this->emailDate = strtotime('-2 days', strtotime($parser->getDate()));

            $localYear = date('Y', strtotime($parser->getDate()));
            $nowYear = date('Y', time());

            if ($localYear === $nowYear) {
                $this->year = $nowYear;
            }
        }

        $this->assignLang();
        $this->ParseFlight($email);

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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        $assignLanguages = array_keys(self::$dictionary);

        foreach ($assignLanguages as $i => $lang) {
            if (!is_string($lang) || empty(self::$dictionary[$lang]['confNumber'])
                || $this->http->XPath->query("//*[{$this->contains(self::$dictionary[$lang]['confNumber'])}]")->length === 0
            ) {
                unset($assignLanguages[$i]);
            }
        }

        if (count($assignLanguages) > 1) {
            foreach ($assignLanguages as $i => $lang) {
                if (!is_string($lang) || empty(self::$dictionary[$lang]['detectBody'])
                    || $this->http->XPath->query("//*[{$this->eq(self::$dictionary[$lang]['detectBody'])}]")->length === 0
                ) {
                    unset($assignLanguages[$i]);
                }
            }
        }

        if (count($assignLanguages) === 1) {
            $this->lang = array_shift($assignLanguages);

            return true;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})[,.\s]*([[:alpha:]]+)[,.\s]*(\d{4})$/u', $text, $m)) {
            // Thu, 17Nov, 2022
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})[,.\s]*([[:alpha:]]+)$/u', $text, $m)) {
            // 17Nov
            $day = $m[1];
            $month = $m[2];
            $year = '';
        } elseif (preg_match('/^([[:alpha:]]+)[,.\s]*(\d{1,2})$/u', $text, $m)) {
            // Nov17
            $month = $m[1];
            $day = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false
                || ($monthNew = MonthTranslate::translate($month, 'en')) !== false
            ) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^([[:upper:]]{2,}){$namePrefixes}(\s+[[:upper:]\s]+)$/u",
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/i",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/i",
        ], [
            '$1$2',
            '$1',
            '$1',
        ], mb_strtoupper($s));
    }
}
