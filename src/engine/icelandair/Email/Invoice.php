<?php

namespace AwardWallet\Engine\icelandair\Email;

class Invoice extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-1.eml, icelandair/it-10288371.eml, icelandair/it-10336627.eml, icelandair/it-10336629.eml, icelandair/it-10400701.eml, icelandair/it-7250778.eml, icelandair/it-7316553.eml, icelandair/it-8906972.eml, icelandair/it-9909813.eml, icelandair/it-9947897.eml, icelandair/it-9983538.eml";

    public $reFrom = "icelandair";
    public $reBody = [
        'en'  => ['BOOKING REFERENCE NUMBER', 'RECEIPT', 't1'],
        'is'  => ['BÓKUNARNÚMERIÐ ÞITT ER', 'GREIÐSLUKVITTUN', 't1'],
        'is2' => ['Kvittun', 'Bókunarnúmer', 't2'],
        'nl'  => ['BOEKINGSREFERENTIE', 'ONTVANGST', 't1'],
        'no'  => ['DITT BESTILLINGSNUMMER ER', 'Kvittering', 't1'],
        'fr'  => ['RÉFÉRENCE DE VOTRE RÉSERVATION', 'CRÉDITÉ À', 't1'],
        'de'  => ['IHRE RESERVIERUNGSNUMMER', 'EMPFANGSBESTÄTIGUNG', 't1'],
    ];
    public $reSubject = [
        'en' => 'Invoice',
        'is' => 'Reikningur/Invoice',
        'nl' => 'Factuur',
        'no' => 'Faktura',
        'fr' => 'Facture',
        'de' => 'Rechnung',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            //			'BOOKING REFERENCE NUMBER' => '',
            //			'Flight' => '',
            //			'From' => '',
            //			'To' => '',
            //			'SALE' => '',
            //			'Date' => '',
            //			'Bókunarnúmer' => '',
            //			'Ticket number' => '',
            //			'Air fare' => '',
            //			'Total airfare' => '',
        ],
        'is' => [
            'BOOKING REFERENCE NUMBER' => 'BÓKUNARNÚMERIÐ ÞITT ER',
            'Flight'                   => 'Flug',
            'From'                     => 'Frá',
            'To'                       => 'Til',
            'SALE'                     => 'VIÐSKIPTI KR',
            'Date'                     => 'Dags',
        ],
        'nl' => [
            'BOOKING REFERENCE NUMBER' => 'BOEKINGSREFERENTIE',
            'Flight'                   => 'Vlucht',
            'From'                     => 'Van',
            'To'                       => 'Naar',
            'SALE'                     => 'TOTAAL',
        ],
        'no' => [
            'BOOKING REFERENCE NUMBER' => 'DITT BESTILLINGSNUMMER ER',
            'Flight'                   => 'Flight',
            'From'                     => 'Fra',
            'To'                       => 'Til',
            'SALE'                     => 'SALG',
        ],
        'fr' => [
            'BOOKING REFERENCE NUMBER' => 'RÉFÉRENCE DE VOTRE RÉSERVATION',
            'Flight'                   => 'Vol(s)',
            'From'                     => 'DE',
            'To'                       => 'À',
            'SALE'                     => 'MONTANT DE LA TRANSACTION',
        ],
        'de' => [
            'BOOKING REFERENCE NUMBER' => 'IHRE RESERVIERUNGSNUMMER',
            'Flight'                   => 'Flug',
            'From'                     => 'Von',
            'To'                       => 'Nach',
            'SALE'                     => 'VERKAUF',
        ],
    ];
    private $date;
    private $emailType = 'type1';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = \AwardWallet\Common\Parser\Util\EmailDateHelper::calculateOriginalDate($this, $parser);
        $this->AssignLang();

        if ($this->emailType == 't2') {
            $its = $this->parseEmail_2();
        } else {
            $its = $this->parseEmail_1();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Invoice' . '_' . $this->emailType . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Icelandair' or contains(@src,'www.icelandair.')] | //text()[contains(.,'Icelandair ehf')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
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
        return stripos($from, $this->reFrom) !== false;
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

            if ('DEZ' === $monthNameOriginal) {
                $date = str_ireplace('DEZ', 'DEC', $date);
            }

            if ('en' !== $this->lang && $translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail_1()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('BOOKING REFERENCE NUMBER'))}]/following::text()[normalize-space(.)!=''][1]");
        $it['Passengers'] = array_map(function ($w) {
            return trim(str_replace("/", " ", $w));
        }, $this->http->FindNodes("//text()[{$this->starts($this->t('BOOKING REFERENCE NUMBER'))}]/ancestor::td[1]//text()[contains(.,')') and contains(.,'(')]", null, "#^(.+?)\([A-Z]+#"));
        $it['Passengers'] = array_filter($it['Passengers']);

        $it['AccountNumbers'] = $this->http->FindNodes("//text()[{$this->starts('Vildarkort')}]", null, "#[\s:]+([A-Z\d]+)#");

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('SALE'))}]/following::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $date = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('Date')}')]", null, true, "#(\d+\.\d+\.\d+)#")));

        if ($date) {
            $this->date = $date;
        }

        if (!$date) {
            $this->logger->info("Year is not detect");

            return [];
        }
        $xpath = "//text()[starts-with(normalize-space(.),'{$this->t('Flight')}') and contains(.,'{$this->t('From')}')]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->info($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("#" . preg_quote($this->t('Flight')) . "\s+([A-Z\d]{2})\s*(\d+)\s+{$this->t('From')}[\s:]+([A-Z]{3})\s+{$this->t('To')}[\s:]+([A-Z]{3})#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['DepCode'] = $m[3];
                $seg['ArrCode'] = $m[4];
            }
            $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1]", $root)));

            if ($seg['DepDate'] < $this->date) {
                $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
            }
            $seg['ArrDate'] = MISSING_DATE;

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function parseEmail_2()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Bókunarnúmer'))}]/following::text()[normalize-space(.)!=''][1]");

        $it['Passengers'] = array_map(function ($w) {
            return trim(str_replace("/", " ", $w));
        }, $this->http->FindNodes("//text()[{$this->starts($this->t('Ticket number'))}]/ancestor::td[1]/preceding-sibling::td[last()][contains(., '/')]"));
        $it['Passengers'] = array_filter($it['Passengers']);

        $it['TicketNumbers'] = $this->http->FindNodes("//text()[{$this->starts('Ticket number')}]", null, "#[\s:]+([A-Z\d \-]+)#");

        $it['BaseFare'] = $this->amount($this->http->FindSingleNode("//text()[{$this->starts('Air fare')}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]"));

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total airfare'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()][4]");

        if (!empty($total)) {
            $it['TotalCharge'] = $this->amount($total);
            $it['Currency'] = $this->currency($total);
        }

        $date = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('Date')}')]", null, true, "#(\d+\.\d+\.\d+)#")));

        if ($date) {
            $this->date = $date;
        }

        if (!$date) {
            $this->logger->info("Year is not detect");

            return [];
        }
        $xpath = "//text()[starts-with(normalize-space(.),'Flight')]/ancestor::tr[1][contains(.,'From')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("#^\s*(?<date>\d{1,2}\s+\w+)\s*\|\s*(?<time>\d+:\d+)\s*Flight\s+(?<airline>[A-Z\d]{2})\s*(?<flightnum>\d+)\s+From:\s*(?<dep>.+)\s+To:\s*(?<arr>.+)#", $node, $m)) {
                $seg['AirlineName'] = $m['airline'];
                $seg['FlightNumber'] = $m['flightnum'];
                $seg['DepName'] = $m['dep'];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrName'] = $m['arr'];
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                $seg['DepDate'] = strtotime($this->normalizeDate($m['date'] . ' ' . $m['time']));

                if ($seg['DepDate'] < $this->date) {
                    $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                }
                $seg['ArrDate'] = MISSING_DATE;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $year = date("Y", $this->date);
        $in = [
            '#^(\d+)\s*(\w+)\s+(\d+:\d+)$#u',
            '#^(\d+)\.(\d+)\.(\d+)$#',
        ];
        $out = [
            '$1 $2 ' . $year . ' $3',
            '$3-$2-$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                 && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);
                    $this->emailType = $reBody[2];

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);			// 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);	// 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '£'=> 'GBP',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = preg_replace("#([,.\d ]+)#", '', $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function amount($s)
    {
        if (empty($s)) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})\b#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }
}
