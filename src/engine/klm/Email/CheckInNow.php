<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CheckInNow extends \TAccountChecker
{
    public $mailFiles = "klm/it-676657778-pt.eml, klm/it-678428091.eml, klm/it-678544437-it.eml, klm/it-687167680.eml, klm/it-777743368.eml";

    public $lang = '';

    public static $dictionary = [
        'it' => [
            'confNumber' => 'codice di prenotazione',
            // 'textAfterCode' => '',
            'Dear'       => 'Gentile',
        ],
        'pt' => [
            // 'confNumber' => '',
            // 'textAfterCode' => '',
            'Dear'       => 'Bom dia',
        ],
        'nl' => [
            'confNumber' => 'boekingscode',
            // 'textAfterCode' => '',
            'Dear'       => 'Beste',
        ],
        'fr' => [
            'confNumber' => 'code de réservation',
            // 'textAfterCode' => '',
            'Dear'       => ['Bonjour', 'Cher Monsieur', 'Chère Madame'],
        ],
        'de' => [
            'confNumber'    => 'den Buchungscode',
            'textAfterCode' => 'online einchecken',
            'Dear'          => 'Guten Tag',
        ],
        'pl' => [
            'confNumber' => 'kod rezerwacji',
            // 'textAfterCode' => '',
            'Dear'       => 'Dzień dobry',
        ],
        'es' => [
            'confNumber' => 'código de reserva',
            // 'textAfterCode' => '',
            'Dear'       => 'Buenos días',
        ],
        'da' => [
            'confNumber' => 'reservationskode',
            // 'textAfterCode' => '',
            'Dear'       => 'Kære',
        ],
        'no' => [
            'confNumber' => 'referansenummeret',
            // 'textAfterCode' => '',
            'Dear'       => 'Kjære',
        ],
        'sv' => [
            'confNumber' => 'bokningskod',
            // 'textAfterCode' => '',
            'Dear'       => 'Hej',
        ],
        'en' => [
            'confNumber' => 'booking code',
            // 'textAfterCode' => '',
            'Dear'       => 'Dear',
        ],
        'uk' => [
            'confNumber' => 'коду бронювання',
            // 'textAfterCode' => '',
            'Dear'       => 'Вітаємо',
        ],
        'fi' => [
            'confNumber' => 'verkossa varauskoodille',
            // 'textAfterCode' => '',
            'Dear'       => 'Hei',
        ],
        'id' => [
            'confNumber' => 'kode pemesanan',
            // 'textAfterCode' => '',
            'Dear'       => 'Yth',
        ],
    ];

    private $subjects = [
        'it' => ['Effettui il check-in per il volo per '],
        'pt' => ['Faça o check-in do seu voo para '],
        'nl' => ['Check in voor uw vlucht naar '],
        'fr' => ['Enregistrez-vous sur votre vol pour '],
        'de' => ['Checken Sie sich ein für Ihren Flug nach '],
        'pl' => ['Proszę dokonać odprawy na lot do '],
        'es' => ['Haga la facturación para su vuelo a '],
        'da' => ['Check ind til din flyvning til '],
        'no' => ['Sjekk inn for flyvningen din til '],
        'sv' => ['Checka in för ditt flyg till '],
        'en' => ['Check in for your flight to'],
        'uk' => ['Пройдіть реєстрацію на рейс до'],
        'fi' => ['Tee lentosi lähtöselvitys lennollesi kohteeseen'],
        'id' => ['Lakukan check in untuk penerbangan Anda ke'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@klm-info.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//title[normalize-space()="KLM Royal Dutch Airlines"]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,".klm.dk/") or contains(@href,".klm.it/") or contains(@href,"www.klm.dk") or contains(@href,"www.klm.it")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"KLM Royal Dutch Airlines")]')->length === 0
        ) {
            return false;
        }

        return $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('CheckInNow' . ucfirst($this->lang));

        $patterns = [
            'date'          => '\b\d{1,2}\s+[[:alpha:]]{3,25}\s*\d{2,4}\b', // 16 May 24    |    16 May 2024 | 20 Październik24
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $f->general()->traveller($traveller);

        $confirmationText = $this->http->FindSingleNode("//text()[{$this->contains($this->tPlusEn('confNumber'))}]");

        if (empty($confirmationText)) {
            $confirmationText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]/following::text()[{$this->contains($this->tPlusEn('confNumber'))}][1]");
        }

        if (preg_match("/\b({$this->opt($this->tPlusEn('confNumber'))})[:\s]+([A-Z\d]{5,8})(?:\s*[,.;:!?]|\s+{$this->opt($this->t('textAfterCode'))}|$)/u", $confirmationText, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $dateDepVal = $this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]", $root);

            if (preg_match("/(?<date>{$patterns['date']})\s+-\s+(?<time>{$patterns['time']})/u", $dateDepVal, $m)) {
                // Thu 16 May 24 - 08:55
                $dateDepNormal = $this->normalizeDate($m['date']);
                $s->departure()->date(strtotime($m['time'], strtotime($dateDepNormal)));
            }

            $airportDep = $this->http->FindSingleNode("tr[normalize-space()][1]", $root);
            $airportArr = $this->http->FindSingleNode("tr[normalize-space()][3]", $root);

            if (preg_match($pattern = "/^(?<city>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*$/", $airportDep, $m)) {
                $s->departure()->name($m['city'])->code($m['code']);
            } else {
                $s->departure()->name($airportDep);
            }

            if (preg_match($pattern, $airportArr, $m)) {
                $s->arrival()->name($m['city'])->code($m['code']);
            } else {
                $s->arrival()->name($airportArr);
            }

            $flight = $this->http->FindSingleNode("tr[normalize-space()][2]", $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            if (!empty($s->getDepDate()) && empty($s->getArrDate())) {
                $s->arrival()->noDate();
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

    private function findSegments(): \DOMNodeList
    {
        $xpathAirportCode = 'translate(.,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")';
        $xpath = "//*[ count(tr[normalize-space()])>1 and count(tr[normalize-space()])<7 and count(tr[normalize-space()][contains(translate({$xpathAirportCode},'() ','∞∞'),'∞∆∆∆∞')]) > 1 ]";
        $this->logger->debug('[XPath] flight segments: ' . $xpath);

        return $this->http->XPath->query($xpath);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Dear'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->starts($phrases['Dear'])}]")->length > 0) {
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

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})[-\s]+([[:alpha:]]+)[-\s]*(\d{2})$/u', $text, $m)) {
            // 16 Maggio 24; 20 Październik24
            $day = $m[1];
            $month = $m[2];
            $year = '20' . $m[3];
        } elseif (preg_match('/^(\d{1,2})[-\s]+([[:alpha:]]+)[-\s]+(\d{4})$/u', $text, $m)) {
            // 16 Maggio 2024
            $day = $m[1];
            $month = $m[2];
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
}
