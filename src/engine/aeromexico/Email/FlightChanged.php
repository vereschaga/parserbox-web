<?php

namespace AwardWallet\Engine\aeromexico\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Engine\MonthTranslate;

class FlightChanged extends \TAccountChecker
{
    public $mailFiles = "aeromexico/it-802126905.eml, aeromexico/it-802702625.eml, aeromexico/it-802543982-es.eml, aeromexico/it-801383472-cancelled.eml";

    private $subjects = [
        'es' => [
            'Se ha demorado tu vuelo a ',
            'Se ha cancelado tu vuelo a ',
            'Información Importante sobre tu vuelo',
        ],
        'en' => [
            'We have assigned to you a new ',
            ' has been cancelled', ' has been canceled',
            ' has been delayed',
        ],
    ];

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'confNumber' => 'Reservación',
            'Departure date' => 'Fecha de salida',
            'Departure time' => 'Hora de Salida',
            'Arrival time' => 'Hora de llegada',
            'Passengers' => 'Pasajeros',
            'cancelledPhrases' => ['Este es tu vuelo cancelado'],
            'cancelledHeader' => 'Cancelado',
        ],
        'en' => [
            'confNumber' => ['Reservation'],
            // 'Departure date' => '',
            // 'Departure time' => '',
            'Arrival time' => ['Arrival time'],
            // 'Passengers' => '',
            'cancelledPhrases' => ['This is your cancelled flight', 'This is your canceled flight'],
            'cancelledHeader' => ['Cancelled', 'Canceled'],
        ]
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aeromexico.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                    return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".aeromexico.com/") or contains(@href,"www.aeromexico.com")]')->length === 0
            && $this->http->XPath->query("//text()[starts-with(normalize-space(),'©') and {$this->contains(['Aeromexico', 'Aeroméxico'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Aeromexico. All the rights reserved', 'Aeroméxico. Todos los derechos reservados'])}]")->length === 0
        ) {
            return false;
        }
        return $this->assignLang() && $this->findSegments()->length > 0;
    }

    private function findSegments(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ count(*)>2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Departure time'))}] and *[normalize-space()][last()]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Arrival time'))}] ]");
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('FlightChanged' . ucfirst($this->lang));

        $xpathNotThrough = 'not(ancestor::*[contains(translate(@style," ",""),"text-decoration:line-through")])';

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,8}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travellers = [];
        $passengersVal = $this->http->FindSingleNode("//*[ count(node()[normalize-space() and not(self::comment())])=2 and node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('Passengers'), "translate(.,':','')")}] ]/node()[normalize-space() and not(self::comment())][2]");
        $passengerList = preg_split('/(?:\s*,\s*)+/', $passengersVal);

        foreach ($passengerList as $tItem) {
            if (preg_match("/^{$patterns['travellerName']}$/u", $tItem)) {
                $travellers[] = $tItem;
            } else {
                $travellers = [];

                break;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('cancelledPhrases'))}]")->length === 1) {
            $f->general()->cancelled();
            $xpathNotThrough = "true()";
        }

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $dateDep = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1][ count(*)>1 and descendant::text()[normalize-space()][1][{$this->eq($this->t('Departure date'))} or {$this->eq($this->t('cancelledHeader'))}] ]/*[normalize-space()][1]/descendant::text()[normalize-space() and {$xpathNotThrough} and not({$this->eq($this->t('Departure date'))}) and not({$this->eq($this->t('cancelledHeader'))})]", $root)));

            $flight = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1][ count(*)>1 and descendant::text()[normalize-space()][1][{$this->eq($this->t('Departure date'))} or {$this->eq($this->t('cancelledHeader'))}] ]/*[normalize-space()][last()]/descendant::text()[normalize-space() and {$xpathNotThrough}]", $root);

            if ( preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m) ) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $timeDep = $timeArr = null;

            /*
                12:20
                Ciudad de Mexico
            */
            $pattern = "/^(?<time>{$patterns['time']})\n+(?<airport>[\s\S]{2,})$/";

            $departureText = implode("\n", $this->http->FindNodes("*[normalize-space()][1]/descendant::text()[normalize-space() and {$xpathNotThrough} and not({$this->eq($this->t('Departure time'))})]", $root));
            $arrivalText = implode("\n", $this->http->FindNodes("*[normalize-space()][last()]/descendant::text()[normalize-space() and {$xpathNotThrough} and not({$this->eq($this->t('Arrival time'))})]", $root));

            if (preg_match($pattern, $departureText, $m)) {
                $timeDep = $m['time'];
                $s->departure()->name(preg_replace('/\s+/', ' ', $m['airport']))->noCode();
            }

            if (preg_match($pattern, $arrivalText, $m)) {
                $timeArr = $m['time'];
                $s->arrival()->name(preg_replace('/\s+/', ' ', $m['airport']))->noCode();
            }

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            if ($dateDep && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateDep));
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
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Arrival time']) ) {
                continue;
            }
            if ($this->http->XPath->query("//node()[{$this->eq($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Arrival time'])}]")->length > 0
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`
     * @param string|null $text Unformatted string with date
     * @return string|null
     */
    private function normalizeDate(?string $text): ?string
    {
        if ( preg_match('/\b(\d{1,2})[-,.\s]+([[:alpha:]]{3,})[-,.\s]+(\d{4})$/u', $text, $m) ) {
            // 24 Nov 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }
        if ( isset($day, $month, $year) ) {
            if ( preg_match('/^\s*(\d{1,2})\s*$/', $month, $m) )
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            if ( ($monthNew = MonthTranslate::translate($month, $this->lang)) !== false )
                $month = $monthNew;
            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }
        return null;
    }
}
