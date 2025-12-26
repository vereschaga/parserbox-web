<?php

namespace AwardWallet\Engine\interjet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class Itinerary2018 extends \TAccountChecker
{
    public $mailFiles = "interjet/it-28972100.eml, interjet/it-29000051.eml, interjet/it-30742791.eml, interjet/it-30912742.eml"; // +1 bcdtravel(html)[es]

    public $detectFrom = '@interjet.com';
    public $detectSubject = [
        'en' => 'Interjet Itinerary',
        'es' => 'Interjet Itinerario',
    ];

    private $detectCompany = 'Interjet';

    private $detectBody = [
        'en' => ['This document is not a boarding pass'],
        'es' => ['Tu clave de reservación es la referencia para obtener'],
    ];

    private $lang = 'en';
    private static $dict = [
        'en' => [],
        'es' => [
            "Booking code"       => "Clave de reservación",
            "Operated by:"       => "Operado por:",
            "Date and time"      => "Fecha y horario",
            "Flight number"      => ["Número de vuelo", "Vuelo"],
            'Departure'          => 'Salida',
            'Arrival'            => 'Llegada',
            "Name"               => "Nombre",
            "Type of passenger"  => "Tipo de pasajero",
            "Seats"              => "Asientos",
            "Interjet base fare" => "Tarifa Interjet",
            "Discounts"          => "Descuentos",
            "Taxes"              => "Impuestos",
            "Total "             => "Total ",
            'Airplane'           => 'Avión',
            'Fare'               => 'Tarifa',
        ],
    ];

    private $typeSegments = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
//        subject included provider name
        //		if (self::detectEmailFromProvider($headers['from']) === false)
        //			return false;

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response['body']);

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $this->typeSegments . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking code")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([\dA-Z]{5,})\s*$#"));

        $passengers = $this->http->FindNodes("//text()[{$this->eq($this->t("Type of passenger"))}]/ancestor::thead[.//text()[{$this->eq($this->t("Name"))}]][1]/following-sibling::tbody/tr/td[1]");

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//text()[{$this->eq($this->t("Type of passenger"))}]/ancestor::tr[.//text()[{$this->eq($this->t("Name"))}]][1]/following-sibling::tr/td[1]");
        }
        $f->general()->travellers($passengers, true);

        // Segments
        $segments = $this->http->XPath->query($xpath = "//tr[ ./*[1][./descendant::text()[{$this->eq($this->t('Date and time'))}]] and ./*[2][./descendant::text()[{$this->eq($this->t('Flight number'))}]] ]");

        if ($segments->length > 0) {
            $this->typeSegments = 1;
            $this->logger->debug($xpath);
        } else {
            $segments = $this->http->XPath->query($xpath = "//tr[ ./*[1][./descendant::text()[{$this->eq($this->t('Departure'))}]] and ./*[2][./descendant::text()[{$this->eq($this->t('Flight number'))}]] ]");

            if ($segments->length > 0) {
                $this->typeSegments = 2;
                $this->logger->debug($xpath);
            }
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            // depCode
            // arrCode
            $headerTexts = $this->http->FindNodes("./ancestor::tr[ ./preceding-sibling::tr[normalize-space(.)] ][1]/preceding-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)]", $root);
            $headerText = implode("\n", $headerTexts);
            // from bcd was broken email with $headerText starts: MEXMID - space loosed ->  \s* between codes
            if (preg_match("/^\s*([A-Z]{3})\s*([A-Z]{3})\s+/", $headerText, $m)) {
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            }

            // airlineName
            if (preg_match("#{$this->preg_implode($this->t('Operated by:'))}\s*(.+)#", $headerText, $m)) {
                $s->airline()->name($m[1]);
            }

            // flightNumber
            $s->airline()->number($this->http->FindSingleNode('./*[2]', $root, true, "#{$this->preg_implode($this->t('Flight number'))}\s*(\d+)\b#"));

            if ($this->typeSegments === 1) {
                // it-28972100.eml, it-29000051.eml
                $this->parseSegment1($s, $root);
            } elseif ($this->typeSegments === 2) {
                // it-30742791.eml, it-30912742.eml
                $this->parseSegment2($s, $root);
            }

            // seats
            if (!empty($s->getFlightNumber())) {
                $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("Type of passenger"))}]/ancestor::thead[.//text()[{$this->eq($this->t("Seats"))}]][1]/following-sibling::tbody/tr/*[3]", null, "#\b{$s->getFlightNumber()}/(\d{1,3}[A-Z])\b#"));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }
        }

        $patterns['amount'] = '/^(?:[^\d)(]+\s*)?(\d[,.\'\d]*)/';

        // Price
        $fare = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Interjet base fare'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", null, true, $patterns['amount']);

        if ($fare !== null) {
            $f->price()->cost($this->normalizeAmount($fare));
        }
        $taxes = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", null, true, $patterns['amount']);

        if ($taxes !== null) {
            $f->price()->tax($this->normalizeAmount($taxes));
        }
        $discounts = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Discounts'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", null, true, $patterns['amount']);

        if ($discounts !== null) {
            $f->price()->discount($this->normalizeAmount($discounts));
        }
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Interjet base fare'))}]/ancestor::table[1]//text()[{$this->starts($this->t('Total '))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", null, true, $patterns['amount']);

        if ($total !== null) {
            $f->price()->total($this->normalizeAmount($total));
        }
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Interjet base fare'))}]/ancestor::table[1]//text()[{$this->starts($this->t('Total '))}]", null, true, "#^\s*{$this->preg_implode($this->t("Total "))}\s*([A-Z]{3})\b#");

        if ($currency) {
            $f->price()->currency($currency);
        }

        return true;
    }

    private function parseSegment1(FlightSegment $s, $root)
    {
        // depDate
        $s->departure()->date($this->normalizeDate($this->http->FindSingleNode('./*[1]', $root, true, "#^\s*{$this->preg_implode($this->t('Date and time'))}\s*(.+)#")));

        // arrDate
        $s->arrival()->noDate();

        // aircraft
        $aircraft = $this->http->FindSingleNode('./*[2]', $root, true, "#{$this->preg_implode($this->t('Flight number'))}\s*\d+\s+(.+)#");
        $s->extra()->aircraft($aircraft);

        // cabin
        $cabin = $this->http->FindSingleNode('./*[3]', $root, true, "#{$this->preg_implode($this->t('Fare'))}\s*(.+)#");
        $s->extra()->cabin($cabin);

        return true;
    }

    private function parseSegment2(FlightSegment $s, $root)
    {
        // depDate
        $s->departure()->date($this->normalizeDate($this->http->FindSingleNode('./*[1]', $root, true, "#^\s*{$this->preg_implode($this->t('Departure'))}\s*(.+)#")));

        // arrDate
        $s->arrival()->date($this->normalizeDate($this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/*[1]', $root, true, "#^\s*{$this->preg_implode($this->t('Arrival'))}\s*(.+)#")));

        // depTerminal
        $terminalDep = $this->http->FindSingleNode('./*[3]', $root, true, "#{$this->preg_implode($this->t('Terminal'))}\s*([A-z\d]+)#i");

        if ($terminalDep !== null) {
            $s->departure()->terminal($terminalDep);
        }

        // aircraft
        $aircraft = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/*[2]', $root, true, "#{$this->preg_implode($this->t('Airplane'))}\s*(.+)#");
        $s->extra()->aircraft($aircraft);

        // cabin
        $cabin = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/*[3]', $root, true, "#{$this->preg_implode($this->t('Fare'))}\s*(.+)#");
        $s->extra()->cabin($cabin, false, true);

        return true;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*([^\s\d]+)\s+(\d+),\s+(\d{4}),\s*(\d+:\d+(?:\s*[AP]M)?)\s*hrs?\s*$#", // January 11, 2019, 22:45 hrs
            "#^\s*(\d+)\s*([^\s\d]+)\s*(\d{4}),\s*(\d+:\d+(?:\s*[AP]M)?)\s*hrs?\s*$#", // 17 noviembre 2018, 07:40 hrs
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if ($this->lang !== 'en' && preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if (($en = MonthTranslate::translate($m[1], $this->lang)) || ($en = MonthTranslate::translate($m[1], 'da')) || ($en = MonthTranslate::translate($m[1], 'no'))) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
