<?php

namespace AwardWallet\Engine\skyair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassNew extends \TAccountChecker
{
    public $mailFiles = "skyair/it-371145485.eml, skyair/it-364485840-es.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'flightDay'          => ['Día de Vuelo:', 'Día de Vuelo :'],
            'confNumber'         => ['Código de reserva:', 'Código de reserva :'],
            'Flight number:'     => 'Número de vuelo:',
            'Departure'          => 'Salida',
            'Arrival'            => 'Llegada',
            'Seat'               => 'Asiento',
            'view boarding pass' => 'Ver tarjeta de embarque',
        ],
        'en' => [
            'flightDay'  => ['Flight day:', 'Flight day :'],
            'confNumber' => ['reservation code:', 'reservation code :', 'Reservation code:'],
        ],
    ];

    private $subjects = [
        'en' => ['Meet our new boarding pass'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@skyairline.com') !== false;
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
        if ($this->http->XPath->query('//a[contains(@href,".skyairline.com/")]')->length === 0) {
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
        $email->setType('BoardingPassNew' . ucfirst($this->lang));

        $f = $email->add()->flight();
        $bp = $email->add()->bpass();

        $s = $f->addSegment();

        $flight = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Flight number:'))}]", null, true, "/^{$this->opt($this->t('Flight number:'))}[:\s]*(.+)$/");

        if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
            $s->airline()->name($m['name'])->number($m['number']);
            $bp->setFlightNumber($m['name'] . ' ' . $m['number']);
        }

        $xpathRoute = "//tr[ not(.//tr) and count(descendant::text()[normalize-space()])=2 and preceding::text()[{$this->starts($this->t('Flight number:'))}] and following::text()[{$this->starts($this->t('flightDay'))}] ]";

        $nameDep = $this->http->FindSingleNode($xpathRoute . "/descendant::text()[normalize-space()][1]");
        $nameArr = $this->http->FindSingleNode($xpathRoute . "/descendant::text()[normalize-space()][2]");

        $s->departure()->name($nameDep);
        $s->arrival()->name($nameArr);

        $date = strtotime($this->normalizeDate($this->http->FindSingleNode("descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('flightDay'))}] ][1]/*[normalize-space()][1]", null, true, "/^{$this->opt($this->t('flightDay'))}[:\s]*(.{6,})$/")));

        $confirmation = $this->http->FindSingleNode("descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->starts($this->t('confNumber'))}] ][1]/*[normalize-space()][2]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
            $bp->setRecordLocator($m[2]);
        }

        $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?'; // 4:19PM    |    2:00 p. m.    |    3pm

        $timeDep = $this->http->FindSingleNode("descendant::*[ count(*[normalize-space()])=3 and *[normalize-space()][1][{$this->starts($this->t('Departure'))}] ][1]/*[normalize-space()][1]", null, true, "/^{$this->opt($this->t('Departure'))}[:\s]*({$patterns['time']})/");
        $timeArr = $this->http->FindSingleNode("descendant::*[ count(*[normalize-space()])=3 and *[normalize-space()][2][{$this->starts($this->t('Arrival'))}] ][1]/*[normalize-space()][2]", null, true, "/^{$this->opt($this->t('Arrival'))}[:\s]*({$patterns['time']})/");

        if ($date && $timeDep) {
            $dateDep = strtotime($timeDep, $date);
            $s->departure()->date($dateDep);
            $bp->setDepDate($dateDep);
        }

        if ($date && $timeArr) {
            $s->arrival()->date(strtotime($timeArr, $date));
        }

        $seat = $this->http->FindSingleNode("descendant::*[ count(*[normalize-space()])=3 and *[normalize-space()][3][{$this->starts($this->t('Seat'))}] ][1]/*[normalize-space()][3]", null, true, "/^{$this->opt($this->t('Seat'))}[:\s]*(\d+[A-z])$/");
        $s->extra()->seat($seat, false, true);

        $xpathBpBtn = "descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->starts($this->t('view boarding pass'))}] ][1]";

        $traveller = $this->http->FindSingleNode($xpathBpBtn . "/*[normalize-space()][1]", null, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");
        $f->general()->traveller($traveller, true);
        $bp->setTraveller($traveller);

        if ($nameDep && $nameArr) {
            $s->departure()->noCode();
            $s->arrival()->noCode();
        }

        $bpUrl = $this->http->FindSingleNode($xpathBpBtn . "/*[normalize-space()][2]/descendant::a[normalize-space() and @href]/@href", null, true, "/^http.+$/i");
        $bp->setUrl($bpUrl);

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
            if (!is_string($lang) || empty($phrases['flightDay']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['flightDay'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
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
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 15/07/2023
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/u',
        ];
        $out = [
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $text);
    }
}
