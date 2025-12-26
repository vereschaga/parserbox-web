<?php

namespace AwardWallet\Engine\volaris\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "volaris/it-10070604.eml, volaris/it-10112963.eml, volaris/it-10114883.eml, volaris/it-40905034.eml, volaris/it-54081475.eml, volaris/it-58007408.eml";

    public $lang = '';
    public $reFrom = ["@volaris.com", "@viaje.volaris.mx", "@experiencias.volaris.mx"];
    public $reSubject = [
        'My Itinerary - Volaris reservations',
        'Your trip is coming up. Print your boarding pass',
        ', tu viaje se acerca. obtén tu pase de abordar',
        'Mi itinerario Volaris',
        ', you\'re about to fly with us, read this information.',
    ];
    public $reBody = [
        'es' => [
            ['Detalles de tu reservación', 'DETALLES DE LA RESERVACIÓN'],
            ['Fecha de vuelo', ''],
        ],
        'en' => [
            ['Reservation details', 'Flight date'],
            ['RESERVATION DETAILS:', 'Flight date'],
        ],
    ];

    public static $dict = [
        'es' => [
            "Reservation code"        => ["Clave de reservación", "Para tu vuelo con número de reservación"],
            "Purchase date"           => "Fecha de compra",
            "Customer Name"           => ["Nombre del Cliente", "Nombre de Cliente"],
            "Customer No"             => "No. Cliente",
            "Customer"                => "Cliente",
            "Total cost of your trip" => "Costo total de tu viaje",
            "Flight date"             => "Fecha de vuelo",
            "Departure / Arrival time"=> "Horario de salida / llegada",
            "Operated by"             => "Operado por",
            "Departure / Arrival:"    => "Origen / Destino:",
            "Seat assignment"         => "Asignación de asiento",
        ],
        'en' => [
            'Reservation code'         => ['Reservation code', 'Volaris Reservation code', 'with reservation number'],
            'Departure / Arrival time' => ['Departure / Arrival time', 'Departure/arrival time'],
        ],
    ];
    private $dateRelative;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->dateRelative = strtotime($parser->getHeader('date'));
        $this->assignLang();
        $this->parseEmail($parser, $email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.volaris.com') or contains(@href,'.volaris.mx')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $from = false;

        if (isset($headers['from'])) {
            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $from = true;

                    break;
                }
            }
        }

        if ($from && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(PlancakeEmailParser $parser, Email $email)
    {
        $f = $email->add()->flight();
        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $f->general()->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation code'))}]/following::text()[normalize-space()!=''][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#"));

        $purchaseDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Purchase date'))}]", null, true, "/{$this->opt($this->t('Purchase date'))}[\s:]+(.+)/"));

        if ($purchaseDate) {
            $f->general()->date($purchaseDate);
            $this->dateRelative = $purchaseDate;
        }

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Customer Name'))}]/ancestor::tr[1][{$this->contains($this->t('Customer No'))}]/following-sibling::tr[string-length(normalize-space())>2]/td[2]", null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u'));

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t("Customer"))} and {$this->contains(')')}]/following::text()[string-length(normalize-space(.))>2][1]");
        }
        $f->general()->travellers($travellers, true);

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total cost of your trip'))}]/following::text()[normalize-space()!=''][1]"));

        if ($tot['Total'] !== null) {
            $f->price()->total($tot['Total']);
            $f->price()->currency($tot['Currency']);
        }

        $xpath = "//text()[{$this->eq($this->t('Flight date'))}]/ancestor::tr[1][{$this->contains($this->t('Departure / Arrival time'))}]/ancestor::tr[1]/descendant::tr[contains(normalize-space(), '/')][not(contains(normalize-space(), '" . $this->t('Flight date') . "'))]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            /* Step 1: checking airports */
            $nameDep = $codeDep = $nameArr = $codeArr = null;
            $routeText = $this->htmlToText($this->http->FindHTMLByXpath('td[3]', null, $root));

            if (preg_match("/(?<DepName>.+?)\s*(?:\bT(?<DepartureTerminal>.))?\s*\((?<DepCode>[A-Z]{3})\)[-\s\/]+(?<ArrName>.+?)\s*(?:\bT(?<ArrivalTerminal>.))?\s*\((?<ArrCode>[A-Z]{3})\)/", $routeText, $m)) {
                $nameDep = $m['DepName'];
                $codeDep = $m['DepCode'];
                $nameArr = $m['ArrName'];
                $codeArr = $m['ArrCode'];
            }

            if ($codeDep === 'TJX' && $nameDep === $codeDep
                || $codeArr === 'TJX' && $nameArr === $codeArr
            ) {
                continue;
            }

            /* Step 2: adding new segment */
            $s = $f->addSegment();

            $flightDate = $this->normalizeDate($this->http->FindSingleNode('*[1]', $root));

            $timeDep = $timeArr = null;
            $times = $this->http->FindSingleNode('*[2]', $root);

            if (preg_match("/^\s*(\d+:\d+(?:\s*[ap]\s*\.?\s*m\.?)?)[\s\/]+(\d+:\d+(?:\s*[ap]\s*\.?\s*?m\.?)?)\s*$/i", $times, $m)) {
                $timeDep = preg_replace('/\s+/', '', $m[1]);
                $timeArr = preg_replace('/\s+/', '', $m[2]);
            }

            if ($flightDate && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $flightDate));
            }

            if ($flightDate && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $flightDate));
            }

            $s->departure()->name($nameDep)->code($codeDep);
            $s->arrival()->name($nameArr)->code($codeArr);

            if (preg_match("/{$this->opt($this->t('Operated by'))}\s+(.+)/", $routeText, $m)) {
                $s->airline()->operator($m[1]);
            }

            $flight = $this->http->FindSingleNode("td[4]", $root);

            if (preg_match("#^\s*(\d+)\s*$#", $flight, $m)) {
                $s->airline()->noName()->number($m[1]);
            } elseif (preg_match("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s*$/", $flight, $m)) {
                $s->airline()->name($m[1])->number($m[2]);
            }

            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $xpathRoute = $this->eq([$s->getDepCode() . ' - ' . $s->getArrCode(), $s->getDepCode() . '-' . $s->getArrCode()]);
                $seats = $this->http->FindNodes("//text()[{$this->eq($this->t('Departure / Arrival:'))}]/following::text()[{$xpathNoEmpty}][1][{$xpathRoute}]/ancestor::tr[1]/following-sibling::tr/descendant::tr[not(.//tr) and {$this->contains($this->t('Seat assignment'))}]", null, "/{$this->opt($this->t('Seat assignment'))}\s*(\d+[A-Z])$/");
                $seats = array_filter($seats);

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        if (preg_match("#[A-Z]{3}#", $node)) {
            $node = str_replace("$", "", $node);
        }
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function normalizeDate($str)
    {
        if ($this->lang == 'es') {
            $str = str_replace('miercoles', 'miércoles', $str);
        }

        $year = $this->dateRelative ? date('Y', $this->dateRelative) : '1970';

        $in = [
            // 01/jul./2022    |    miércoles 17 ago. 2022
            '/^(?:[-[:alpha:]]+[,.\s]*)?(\d{1,2})[.\s\/]+([[:alpha:]]{3,})[.\s\/]+(\d{4}).*/u',
            // 10 feb. 22    |    miércoles 17 ago. 22
            '/^(?:[-[:alpha:]]+[,.\s]*)?(\d{1,2})[.\s\/]+([[:alpha:]]{3,})[.\s\/]+(\d{2}).*/u',
            // miercoles 28 sep.
            '/^([-[:alpha:]]+)[,.\s]*(\d{1,2})\s*([[:alpha:]]+)[.\s]*$/u',
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 20$3',
            "$1, $2 $3 $year",
        ];

        foreach ($in as $i => $re) {
            if (preg_match($re, $str)) {
                $str = preg_replace($re, $out[$i], $str);

                break;
            }
        }

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
