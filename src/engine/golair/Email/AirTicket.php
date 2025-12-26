<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "golair/it-3695937.eml, golair/it-3851490.eml, golair/it-5163642.eml, golair/it-5201767.eml, golair/it-5547277.eml, golair/it-6104824.eml, golair/it-6107253.eml, golair/it-6352843.eml";

    public $reBody = [
        'pt' => ['ITINERÁRIO', 'Chegada'],
        'es' => ['ITINERARIO', 'Liegada'],
        'en' => ['ITINERARY', 'Destination'],
    ];
    public $compareDate;
    public $lang = '';
    public static $dict = [
        'pt' => [],
        'es' => [
            'Data da Compra'        => 'Fecha de la compra',
            'Data'                  => 'Fecha',
            'Voo'                   => 'Vuelo',
            'Nome'                  => 'Nombre',
            'TOTAL DA VIAGEM'       => 'TOTAL DEL VIAJE',
            'Situação do Pagamento' => 'Situacíon del pago',
        ],
        'en' => [
            'Data da Compra'        => 'Purchase date',
            'Data'                  => 'Date',
            'Voo'                   => ['Flight', 'Fligth'],
            'Nome'                  => 'Name',
            'TOTAL DA VIAGEM'       => 'TRIP TOTAL',
            'Situação do Pagamento' => 'Payment status',
            'LOCALIZADOR GOL'       => 'GOL LOCATOR',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->compareDate = strtotime($parser->getDate());

        foreach ($this->reBody as $lang => $reBody) {
            if ($this->http->XPath->query('//text()[normalize-space(.)="' . $reBody[0] . '"]')->length > 0 && $this->http->XPath->query('//text()[normalize-space(.)="' . $reBody[1] . '"]')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'comunicacaovoegol@voegol.com.br') !== false
            || stripos($headers['subject'], 'Alerta GOL - Itinerário de Viagem') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@voegol.com.br') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".voegol.com.br")] | //text()['.$this->eq(['GOL Linhas Aéreas SA']).']')->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query('//text()[normalize-space(.)="' . $reBody[0] . '"]')->length > 0 && $this->http->XPath->query('//text()[normalize-space(.)="' . $reBody[1] . '"]')->length > 0) {
                    return true;
                }
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $xpathPax = "//tr[not(.//tr) and *[self::td or self::th][{$this->contains($this->t('Nome'))}] and *[self::td or self::th][{$this->contains($this->t('Voo'))}]]/ancestor-or-self::table[1]/descendant::tr[not(*[self::td or self::th][{$this->contains($this->t('Nome'))}] and *[self::td or self::th][{$this->contains($this->t('Voo'))}]) and count(td[normalize-space()])=4]";

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode('//text()[' . $this->contains($this->t('LOCALIZADOR GOL')) . ']/following::text()[normalize-space(.)][1]'))
        ;
        $status = $this->http->FindSingleNode('//p[' . $this->contains($this->t('Situação do Pagamento')) . ']', null, true, '/^[^:]+[:\s]+([^:]+\S)/');

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->contains($this->t('Data da Compra')) . "]/following::text()[normalize-space(.)][1]"));

        if (!empty($date)) {
            $f->general()->date($date);
            $this->compareDate = $date;
        }

        $travellers = [];
        $tickets = [];
        $travellersNodes = $this->http->XPath->query($xpathPax);

        foreach ($travellersNodes as $pRoot) {
            $name = $this->http->FindSingleNode('./td[normalize-space()][1]', $pRoot);
            $number = $this->http->FindSingleNode('./td[normalize-space()][4]', $pRoot, true, '/^\s*([-A-Z\d\s]+)\s*$/');
            $travellers[] = preg_replace('/^[\d]+\. /', '', $name);
            $tickets[] = $number;
        }
        $f->general()
            ->travellers(array_values(array_unique($travellers)));

        // Issued
        $f->issued()
            ->tickets(array_values(array_unique($tickets)), false);

        // Price
        $total = implode(' ', $this->http->FindNodes('//td[not(.//td) and ' . $this->contains($this->t('TOTAL DA VIAGEM')) . ']/following-sibling::td'));

        if (!empty($total)) {
            $tot = $this->getTotalCurrency($total);
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency'])
            ;
        }

        // Segments
        $xpath = "//tr[not(.//tr) and *[self::td or self::th][{$this->contains($this->t('Data'))}] and *[self::td or self::th][{$this->contains($this->t('Voo'))}]]/ancestor-or-self::table[1]/descendant::tr[not(*[self::td or self::th][{$this->contains($this->t('Data'))}] and *[self::td or self::th][{$this->contains($this->t('Voo'))}]) and count(td[normalize-space()])>5 and count(td[normalize-space()])<9 and contains(.,':')]";
        $segments = $this->http->XPath->query($xpath);
        //		$this->logger->debug('Xpath: '. $xpath);
        if ($segments->length === 0) {
            return false;
        }

        foreach ($segments as $row) {
            $s = $f->addSegment();

            $nodes = $this->http->XPath->query('./../tr[count(td[normalize-space()])=7 or count(td[normalize-space()])=8]', $row);
            $plusTd = $nodes->length > 0 ? 1 : 0;

            if ($this->http->XPath->query('preceding::tr[contains(., "Embarque")]/../following-sibling::*[1]/tr[count(td[normalize-space()]) = 7]', $row)->length > 0) {
                $plusTd = 0;
            }

            $date = $this->http->FindSingleNode('./td[normalize-space()][1+' . $plusTd . ']', $row);

            // Airline
            $airFlightIndex = $this->http->FindSingleNode('./td[normalize-space()][2+' . $plusTd . ']', $row);

            if (preg_match('/(\w+)\s+(\d+)/u', $airFlightIndex, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
                $seats = array_filter($this->http->FindNodes("{$xpathPax}/td[normalize-space()][2][normalize-space(.)='" . $m[1] . ' ' . $m[2] . "']/following-sibling::td[normalize-space()][1]", null, "#^\s*(\d+\w)\s*$#"));

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }

            // Departure
            $depCodeName = $this->http->FindSingleNode('./td[normalize-space()][3+' . $plusTd . ']', $row);

            if (preg_match('/(?<name>[-\w\s]+)\s+\((?<code>[\w]{3})\)/u', $depCodeName, $m)) {
                $s->departure()
                    ->name(trim($m['name'], ' ,-'))
                    ->code($m['code'])
                ;
            }
            $timeDep = $this->http->FindSingleNode('(./td[normalize-space()][position()>4 and contains(.,":") and string-length(normalize-space(.))>3 and string-length(normalize-space(.))<6])[last()]/preceding-sibling::*[string-length(normalize-space(.))>3][1]', $row, true, '/(\d{1,2}:\d{2})/');

            if (!empty($timeDep) && !empty($date)) {
                $s->departure()
                    ->date(strtotime($timeDep, $this->normalizeDate($date, true)));
            }

            // Arrival
            $arrCodeName = $this->http->FindSingleNode('./td[normalize-space()][4+' . $plusTd . ']', $row);

            if (preg_match('/(?<name>[-\w\s]+)\s+\((?<code>[\w]{3})\)/u', $arrCodeName, $m)) {
                $s->arrival()
                    ->name(trim($m['name'], ' ,-'))
                    ->code($m['code'])
                ;
            }
            $timeArr = $this->http->FindSingleNode('(./td[normalize-space()][position()>4 and contains(.,":") and string-length(normalize-space(.))>3 and string-length(normalize-space(.))<6])[last()]', $row, true, '/(\d{1,2}:\d{2})/');

            if (!empty($timeArr) && !empty($date)) {
                $s->arrival()
                    ->date(strtotime($timeArr, $this->normalizeDate($date, true)));
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date, $correct = false)
    {
        $year = date('Y', $this->compareDate);
        $in = [
            '/^\s*(\d+)\s+(\S+)\s*$/',
        ];
        $out = [
            '$1 $2 ' . $year,
        ];

        switch ($this->lang) {
            case 'en'://'en' - 05/21 - date format
                array_push($in, '/^\s*(\d+)\/(\d+)\s*$/');
                array_push($out, '$2.$1.' . $year);

                array_push($in, '/^\s*(\d+)\/(\d+)\/(\d+)\s*$/');
                array_push($out, '$2.$1.$3');

                break;

            default://'pt' - 17/03 - date format
                array_push($in, '/^\s*(\d+)\/(\d+)\s*$/');
                array_push($out, '$1.$2.' . $year);

                array_push($in, '/^\s*(\d+)\/(\d+)\/(\d+)\s*$/');
                array_push($out, '$1.$2.$3');

                break;
        }
        $date = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        if ($correct == true) {
            $date = EmailDateHelper::parseDateRelative(' ', $this->compareDate, true, $date);
        } else {
            $date = strtotime($date);
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace([' ', ','], ['', '.'], $m['t']);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field, $node = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(' . $node . ',"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
