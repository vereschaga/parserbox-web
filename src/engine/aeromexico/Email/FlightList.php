<?php

namespace AwardWallet\Engine\aeromexico\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Engine\MonthTranslate;

class FlightList extends \TAccountChecker
{
    public $mailFiles = "aeromexico/it-799665701-es.eml, aeromexico/it-801821469-es.eml, aeromexico/it-799097501-es.eml";

    private $subjects = [
        'es' => [
            'Descarga aquí tu pase de abordar y evita contratiempos',
            'Haz check-in y evita filas en el aeropuerto',
            'Haz check in y evita filas en el aeropuerto',
        ],
    ];

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'confNumber' => ['Reservación'],
            'hello' => 'Hola',
            'departureDate' => 'Fecha de salida',
            'departureTime' => 'Hora de salida',
            'arrivalTime' => ['Hora de llegada'],
        ],
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
            && $this->http->XPath->query("//text()[starts-with(normalize-space(),'©') and {$this->contains(['Aeroméxico'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Aeroméxico. Todos los derechos reservados'])}]")->length === 0
        ) {
            return false;
        }
        return $this->assignLang() && $this->findRoots()->length > 0;
    }

    private function findRoots(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ count(*)>2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('departureTime'))}] and *[normalize-space()][last()]/descendant::text()[normalize-space()][1][{$this->eq($this->t('arrivalTime'))}] ]");
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('FlightList' . ucfirst($this->lang));

        $patterns = [
            'date' => '\b\d{1,2}[,.\s]+[[:alpha:]]{3,23}[,.\s]+\d{4}\b', // 27 nov 2024
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:upper:]][-\'’[:upper:] ]*[[:upper:]]', // VIVIAN EMMA QUESADA GAMA
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,8}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('hello'))}]", null, "/^{$this->opt($this->t('hello'))}[,\s]+({$patterns['travellerName']}(?:\s*,\s*{$patterns['travellerName']})*)(?:\s*[,.;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $travellersVal = array_shift($travellerNames);
            $travellers = preg_split('/(\s*,\s*)+/', $travellersVal);
            $f->general()->travellers($travellers);
        }

        $flight1 = $flight2 = null;
        $flightsVal = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Está todo listo para tu próximo vuelo'))}]/following::text()[normalize-space()][1]", null, true, "/^(.*\d)[.;!?\s]*$/");
        
        if (preg_match("/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+)\s*,\s*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+)$/", $flightsVal, $m)) {
            // AM0204, AM0229
            $flight1 = $m[1];
            $flight2 = $m[2];
        } elseif (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*,\s*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+)\s*,\s*(\d+)$/", $flightsVal, $m)) {
            // AM, AM0204, 0229
            $flight1 = $m[2];
            $flight2 = $m[1] . $m[3];
        } elseif (preg_match("/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+$/", $flightsVal)) {
            // AM0204
            $flight1 = $flightsVal;
        }

        $roots = $this->findRoots();

        if ($roots->length !== 1) {
            $this->logger->debug('Root-node not found!');

            return $email;
        }
        $root = $roots->item(0);

        $dateDep1 = $dateDep2 = $timeDep1 = $timeDep2 = $timeArr1 = $timeArr2 = $airportsDep = $airportsArr = null;

        $datesDepVal = $this->http->FindSingleNode("preceding::tr[count(*[normalize-space()])>1][1][ *[normalize-space()][1][{$this->eq($this->t('departureDate'))}] ]/*[normalize-space()][last()]", $root, true, "/^(.*?)[,\s]*$/");
        
        if (preg_match("/^(?<date1>{$patterns['date']})\s*,\s*(?<date2>{$patterns['date']})$/u", $datesDepVal, $m)) {
            // 25 nov 2024, 27 nov 2024
            $dateDep1 = strtotime($this->normalizeDate($m['date1']));
            $dateDep2 = strtotime($this->normalizeDate($m['date2']));
        } elseif (preg_match("/^{$patterns['date']}$/u", $datesDepVal)) {
            // 27 nov 2024
            $dateDep1 = $dateDep2 = strtotime($this->normalizeDate($datesDepVal));
        } 

        $departureText = implode("\n", $this->http->FindNodes("*[normalize-space()][1]/descendant::text()[normalize-space() and not({$this->eq($this->t('departureTime'))})]", $root));
        $arrivalText = implode("\n", $this->http->FindNodes("*[normalize-space()][last()]/descendant::text()[normalize-space() and not({$this->eq($this->t('arrivalTime'))})]", $root));

        /*
            08:29 AM, 03:05 PM,
            GUADALAJARA, MEXICO, MEXICO CITY, MEXICO
        */
        $pattern1 = "/^(?<time1>{$patterns['time']})\s*,\s*(?<time2>{$patterns['time']})[, ]*\n+(?<airports>[\s\S]{2,}?)[,\s]*$/";

        /*
            03:05 PM,
            MEXICO CITY, MEXICO
        */
        $pattern2 = "/^(?<time>{$patterns['time']})[, ]*\n+(?<airport>[\s\S]{2,}?)[,\s]*$/";

        if (preg_match($pattern1, $departureText, $m)) {
            $timeDep1 = $m['time1'];
            $timeDep2 = $m['time2'];
            $airportsDep = preg_replace('/\s+/', ' ', $m['airports']);
        } elseif (preg_match($pattern2, $departureText, $m)) {
            $timeDep1 = $m['time'];
            $airportsDep = preg_replace('/\s+/', ' ', $m['airport']);
        }

        if (preg_match($pattern1, $arrivalText, $m)) {
            $timeArr1 = $m['time1'];
            $timeArr2 = $m['time2'];
            $airportsArr = preg_replace('/\s+/', ' ', $m['airports']);
        } elseif (preg_match($pattern2, $arrivalText, $m)) {
            $timeArr1 = $m['time'];
            $airportsArr = preg_replace('/\s+/', ' ', $m['airport']);
        }

        $noFlightNumbers = $this->http->XPath->query("//text()[{$this->contains($this->t('Está todo listo para tu próximo vuelo'))}]")->length === 0 && $this->http->XPath->query("//text()[{$this->contains($this->t('obtener tu pase de abordar'))}]")->length === 1;
        $twoSegments = $timeDep2 !== null || $timeArr2 !== null;

        $airportDep1 = $airportDep2 = $airportArr1 = $airportArr2 = null;

        if ($twoSegments) {
            /*
                MEXICO CITY DE MEXICO, Ciudad de México
            */
            $re1 = "/^([^,]{2,}?)\s*,\s*([^,]{2,})$/";

            /*
                MEXICO CITY, MEXICO, GUADALAJARA, MEXICO
            */
            $re2 = "/^([^,]{2,}\s*,\s*[^,]{2,}?)\s*,\s*([^,]{2,}\s*,\s*[^,]{2,})$/";

            if (preg_match($re1, $airportsDep, $m) || preg_match($re2, $airportsDep, $m)) {
                $airportDep1 = $m[1];
                $airportDep2 = $m[2];
            }

            if (preg_match($re1, $airportsArr, $m) || preg_match($re2, $airportsArr, $m)) {
                $airportArr1 = $m[1];
                $airportArr2 = $m[2];
            }
        } else {
            $airportDep1 = $airportsDep;
            $airportArr1 = $airportsArr;
        }

        /* 1st segment */

        $s = $f->addSegment();

        if ( preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight1, $m) ) {
            $s->airline()->name($m['name'])->number($m['number']);
        } elseif ($noFlightNumbers) {
            $s->airline()->noName()->noNumber();
        }

        if ($dateDep1 && $timeDep1) {
            $s->departure()->date(strtotime($timeDep1, $dateDep1));
        }

        if ($dateDep1 && $timeArr1) {
            $s->arrival()->date(strtotime($timeArr1, $dateDep1));
        }

        $s->departure()->name($airportDep1)->noCode();
        $s->arrival()->name($airportArr1)->noCode();

        if (!$twoSegments) {
            return $email;
        }

        /* 2nd segment */

        $s = $f->addSegment();

        if ( preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight2, $m) ) {
            $s->airline()->name($m['name'])->number($m['number']);
        } elseif ($noFlightNumbers) {
            $s->airline()->noName()->noNumber();
        }

        if ($dateDep2 && $timeDep2) {
            $s->departure()->date(strtotime($timeDep2, $dateDep2));
        }

        if ($dateDep2 && $timeArr2) {
            $s->arrival()->date(strtotime($timeArr2, $dateDep2));
        }

        $s->departure()->name($airportDep2)->noCode();
        $s->arrival()->name($airportArr2)->noCode();

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
            if ( !is_string($lang) || empty($phrases['confNumber']) || empty($phrases['arrivalTime']) ) {
                continue;
            }
            if ($this->http->XPath->query("//node()[{$this->eq($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['arrivalTime'])}]")->length > 0
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

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
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
